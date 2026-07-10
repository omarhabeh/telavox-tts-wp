(function () {
  "use strict";

  var cfg = window.ttsPortal || {};
  var $ = function (id) {
    return document.getElementById(id);
  };

  function api(path, options) {
    options = options || {};
    var headers = Object.assign(
      {
        "X-WP-Nonce": cfg.nonce || "",
      },
      options.headers || {}
    );
    return fetch((cfg.restUrl || "") + path, Object.assign({}, options, { headers: headers }));
  }

  function showError(msg) {
    $("appError").textContent = msg || "";
  }

  function filenameFrom(text) {
    var base =
      text
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/gi, "")
        .trim()
        .replace(/\s+/g, "-")
        .slice(0, 40) || "tts";
    return base + ".mp3";
  }

  async function loadVoices() {
    var res = await api("voices");
    if (res.status === 401) {
      window.location.href = "/wp-login.php?redirect_to=" + encodeURIComponent(window.location.href);
      return;
    }
    if (!res.ok) {
      throw new Error("Could not load voices");
    }
    var data = await res.json();
    var sel = $("voice");
    sel.innerHTML = "";
    (data.voices || []).forEach(function (v) {
      var opt = document.createElement("option");
      opt.value = v.id;
      opt.textContent = v.label;
      sel.appendChild(opt);
    });
  }

  var lastUrl = null;

  async function generate() {
    var text = $("text").value.trim();
    var voiceId = $("voice").value;
    showError("");
    if (!text) {
      showError("Type some text first.");
      return;
    }

    var btn = $("generateBtn");
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Generating...';
    try {
      var res = await api("tts", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ text: text, voiceId: voiceId }),
      });
      if (res.status === 401) {
        window.location.href = "/wp-login.php?redirect_to=" + encodeURIComponent(window.location.href);
        return;
      }
      if (!res.ok) {
        var msg = "TTS failed";
        try {
          var err = await res.json();
          msg = (err && (err.message || err.error)) || msg;
        } catch (e) {
          /* ignore */
        }
        throw new Error(msg);
      }
      var blob = await res.blob();
      if (lastUrl) URL.revokeObjectURL(lastUrl);
      lastUrl = URL.createObjectURL(blob);

      $("player").src = lastUrl;
      var link = $("downloadLink");
      link.href = lastUrl;
      link.download = filenameFrom(text);
      $("result").classList.remove("hidden");
      $("player").play().catch(function () {});
    } catch (e) {
      showError(e.message || "TTS failed");
    } finally {
      btn.disabled = false;
      btn.textContent = "Generate";
    }
  }

  var who = cfg.userName || "";
  if (cfg.userEmail && cfg.userName && cfg.userEmail !== cfg.userName) {
    who = cfg.userName + " · " + cfg.userEmail;
  } else if (!who && cfg.userEmail) {
    who = cfg.userEmail;
  }
  $("whoami").textContent = who;
  $("logoutBtn").href = cfg.logoutUrl || "/wp-login.php?action=logout";
  $("generateBtn").addEventListener("click", generate);
  $("redoBtn").addEventListener("click", generate);

  loadVoices().catch(function (e) {
    showError(e.message || "Could not load voices");
  });
})();
