(function () {
  var heading = document.querySelector(".tts-login-heading");
  var form = document.querySelector("#loginform, #lostpasswordform, #resetpassform");
  if (heading && form) {
    form.insertBefore(heading, form.firstChild);
  }
})();
