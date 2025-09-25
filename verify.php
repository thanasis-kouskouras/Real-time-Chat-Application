<?php
ini_set('display_errors', 0); // disable error display
ini_set('log_errors', 0); // disable error logging
$error = '';
$success = '';

require_once "includes/functions.inc.php";
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['code'])) {
    $isValid = validateVerificationToken(filter_var($_GET['code'], FILTER_SANITIZE_STRING));

    if ($isValid) {
        $_SESSION['message'] = 'Your Email has been verified, now you can login into EasyTalk chat Application';
    } else {
        if (!isset($_SESSION['error']))
            $_SESSION['error'] = 'Your Email verification has error. Please try again.';
    }
    header('location:login.php');
} else if (isset($_POST["email"])) {

    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    $emailExists = emailExists($email);
    if ($email !== false && $emailExists) {
        $isVerified = isVerified($email);
        if ($isVerified) {
            setSessionError("Email already verified!");
            header('location:login.php');
            exit();
        } else {
            // send a new verification link
            list($success, $error) = generate_verification_link($email);
            if ($success) {
                header("location: login.php");
                exit();
            }
        }
    } else {
        setSessionError("Email not correct to generate verification link.");
        header('location:login.php');
        exit();

    }
}

