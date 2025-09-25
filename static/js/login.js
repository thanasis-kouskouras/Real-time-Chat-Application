function regenerate_verify_email(email) {
  postRedirect("verify.php", { email: email });
}

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
  console.log(form_part);
  const form = $(form_part);
  $("body").append(form);
  $(form).submit();
}
