function viewPassword(elem, eye = "eye") {
  const password = document.getElementById(elem);
  if (password.type === "password") {
    password.type = "text";
    document.getElementById(eye).style.color = "#0d6efd";
  } else {
    password.type = "password";
    document.getElementById(eye).style.color = "#7a797e";
  }
}
