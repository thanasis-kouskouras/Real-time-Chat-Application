/* AUTHENTICATION UTILITIES

Handles password visibility toggle and login helpers. */

//Password visibility toggle
function viewPassword(elem, eye = "eye") {
  const password = document.getElementById(elem);
  const eyeEl = document.getElementById(eye);
  if (password.type === "password") {
    password.type = "text";
    eyeEl.classList.add("eye-active");
    eyeEl.classList.remove("eye-inactive");
  } else {
    password.type = "password";
    eyeEl.classList.remove("eye-active");
    eyeEl.classList.add("eye-inactive");
  }
}

//Email verification redirect
function regenerate_verify_email(email) {
  postRedirect("verify.php", { email: email });
}

//Post redirect helper
function postRedirect(redirectUrl, obj) {
  let input_part = "";
  for (let id in obj) {
    input_part +=
      '<input type="hidden" name="' + id + '" value="' + obj[id] + '">';
  }
  const form_part =
    '<form action="' +
    redirectUrl +
    '" method="post">' +
    input_part +
    "</form>";
  const form = $(form_part);
  $("body").append(form);
  $(form).submit();
}

//Export for backward compatibility
window.viewPassword = viewPassword;
window.regenerate_verify_email = regenerate_verify_email;
window.postRedirect = postRedirect;
