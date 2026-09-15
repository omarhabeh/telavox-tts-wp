(function () {
  "use strict";

  function setupRepeater(rowsId, addBtnId, templateId, optionName) {
    var rows = document.getElementById(rowsId);
    var addBtn = document.getElementById(addBtnId);
    var template = document.getElementById(templateId);

    if (!rows || !addBtn || !template) {
      return;
    }

    var namePattern = new RegExp(optionName + "\\[\\d+\\]");

    function nextIndex() {
      return rows.querySelectorAll(".tts-repeater-row").length;
    }

    function reindex() {
      Array.prototype.forEach.call(rows.querySelectorAll(".tts-repeater-row"), function (row, i) {
        Array.prototype.forEach.call(row.querySelectorAll("input[name]"), function (input) {
          input.name = input.name.replace(namePattern, optionName + "[" + i + "]");
        });
      });
    }

    addBtn.addEventListener("click", function () {
      var html = template.innerHTML.replace(/__i__/g, String(nextIndex()));
      rows.insertAdjacentHTML("beforeend", html);
    });

    rows.addEventListener("click", function (e) {
      var btn = e.target.closest(".tts-repeater-remove");
      if (!btn) {
        return;
      }
      var row = btn.closest(".tts-repeater-row");
      if (!row) {
        return;
      }
      if (rows.querySelectorAll(".tts-repeater-row").length <= 1) {
        row.querySelectorAll("input").forEach(function (input) {
          input.value = "";
        });
        return;
      }
      row.remove();
      reindex();
    });
  }

  setupRepeater("tts-voices-rows", "tts-add-voice", "tts-voice-row-template", "tts_portal_voices");
  setupRepeater("tts-languages-rows", "tts-add-language", "tts-language-row-template", "tts_portal_languages");
})();
