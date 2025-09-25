<?php

require_once 'functions.inc.php';
if (isset($_POST["forgot-password-submit"])) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $userEmail = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    if (empty($userEmail) !== false) {
        header("location: ../forgot-password.php?error=emptyinput");
        exit();
    } else if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL) !== false) {
        header("location: ../forgot-password.php?error=invalidemail");
        exit();
    }
    list($rowData, $error) = getUserByEmail($userEmail);
    if ($error !== "") {
        setSessionError($error);
        header("location: ../forgot-password.php");
        exit();
    }

    if (!is_array($rowData)) {
        header("location: ../forgot-password.php?error=emailnotexist");
        exit();
    }

    list($rowData, $error) = deletePasswordReset($userEmail);
    if ($error !== "") {
        setSessionError($error);
        header("location: ../forgot-password.php");
        exit();
    }
    try {
        list($success, $error) = createPasswordReset($userEmail);
    } catch (Exception $e) {
        $success = false;
    }
    if ($error !== "") {
        setSessionError($error);
        header("location: ../forgot-password.php");
    }else if ($success) {
        list($success, $error) = reset_email($userEmail);
        if ($error !== "") {
            setSessionError($error);
            header("location: ../forgot-password.php");
        } else if ($success){
            if(isset($_SESSION['message']))
                unset($_SESSION['message']); // this is a success message only applied to login page
            header("location: ../forgot-password-emailcheck.php");
        }
        else
            header("location: ../forgot-password.php");
    } else //else  go back something happended
        header("location: ../forgot-password.php");
} else {
    header("Location: ../login.php");
}
exit();