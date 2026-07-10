(function () {
  "use strict";

  var rows = document.getElementById("tts-voices-rows");
  var addBtn = document.getElementById("tts-add-voice");
  var template = document.getElementById("tts-voice-row-template");

  if (!rows || !addBtn || !template) {
    return;
  }

  function nextIndex() {
    return rows.querySelectorAll(".tts-voice-row").length;
  }

  function reindex() {
    Array.prototype.forEach.call(rows.querySelectorAll(".tts-voice-row"), function (row, i) {
      Array.prototype.forEach.call(row.querySelectorAll("input[name]"), function (input) {
        input.name = input.name.replace(/tts_portal_voices\[\d+\]/, "tts_portal_voices[" + i + "]");
      });
    });
  }

  addBtn.addEventListener("click", function () {
    var html = template.innerHTML.replace(/__i__/g, String(nextIndex()));
    rows.insertAdjacentHTML("beforeend", html);
  });

  rows.addEventListener("click", function (e) {
    var btn = e.target.closest(".tts-remove-voice");
    if (!btn) {
      return;
    }
    var row = btn.closest(".tts-voice-row");
    if (!row) {
      return;
    }
    if (rows.querySelectorAll(".tts-voice-row").length <= 1) {
      row.querySelectorAll("input").forEach(function (input) {
        input.value = "";
      });
      return;
    }
    row.remove();
    reindex();
  });
})();
