<?php


if (isset($_POST["signup-submit"])) {

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $username = filter_var($_POST["username"], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    $password = filter_var($_POST["password"], FILTER_SANITIZE_STRING);
    $confirmPassword = filter_var($_POST["confirm-password"], FILTER_SANITIZE_STRING);

    require_once 'dbh.inc.php';
    require_once 'functions.inc.php';

    if (emptyInputSignup($username, $email, $password, $confirmPassword) !== false) {
        header("location: ../signup.php?error=emptyinputsignup");
        exit();
    }
    if (invalidUsername($username) !== false) {
        header("location: ../signup.php?error=invalidusername&email=$email");
        exit();
    }
    if (invalidEmail($email) !== false) {
        header("location: ../signup.php?error=invalidemail&username=$username");
        exit();
    }

    if ($password === "" || invalidPassword($password) !== false) {
        header("location: ../signup.php?error=invalidPassword&username=$username&email=$email");
        exit();
    }

    if (!passwordMatch($password, $confirmPassword)) {
        header("location: ../signup.php?error=passwordsdontmatch&username=$username&email=$email");
        exit();
    }
    if (emailExists($email)) {
        header("location: ../signup.php?error=emailtaken&username=$username");
        exit();
    }
    list($token, $error) = createUser($username, $email, $password);
    if ($error !== "") {
        $_SESSION['error'] = $error;
        header("location: ../signup.php");
    }
    header("location: ../login.php?error=none");
} else {
    header("location: ../signup.php");
}
exit();