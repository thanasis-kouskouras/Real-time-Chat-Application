<?php

require '../protect.php';

if (!isset($_SESSION["UserID"])) {
    header("location: ../login.php");
    exit();
}
$userid = $_SESSION["UserID"];


if (isset($_POST["change-username-submit"])) {

    $newUsername = filter_var($_POST["newUsername"], FILTER_SANITIZE_STRING);

    require_once 'functions.inc.php';

    if (emptyInputNewUsername($newUsername) !== false) {
        header("location: ../profile.php?error=emptyInputNewUsername");
        exit();
    }
    if (invalidUsername($newUsername) !== false) {
        header("location: ../profile.php?error=invalidNewUid");
        exit();
    }

    if (!changeUsername_($newUsername, $userid))
        header("location: ../profile.php?error=stmtfailed");
    header("location: ../profile.php?error=nuidNone");

} else if (isset($_POST["change-password-submit"])) {

    $password = filter_var($_POST["password"], FILTER_SANITIZE_STRING);
    $newPassword = filter_var($_POST["newPassword"], FILTER_SANITIZE_STRING);
    $newPasswordRepeat = filter_var($_POST["newPasswordRepeat"], FILTER_SANITIZE_STRING);

    require_once 'functions.inc.php';

    if (emptyInputNewPassword($password, $newPassword, $newPasswordRepeat) !== false) {
        header("location: ../profile.php?error=emptyInputNewPassword");
        exit();
    }
    if (isPasswordCorrect($password, $userid) !== true) {
        header("location: ../profile.php?error=wrongOldPassword");
        exit();
    }

    if (invalidPassword($newPassword) !== false) {
        header("location: ../profile.php?error=invalidNewPassword");
        exit();
    }

    if ($newPassword !== $newPasswordRepeat) {
        header("location: ../profile.php?error=newpasswordsdontmatch");
        exit();
    }


    list($success, $error) = changePassword($newPassword, $userid);
    if ($error !== "") {
        setSessionError($error);
        header("location: ../profile.php");
    }
    else if (!$success) {
        header("location: ../profile.php?error=stmtfailed");
    } else {
        $_SESSION['message'] = "Successfully changed password!";
        header("location: ../profile.php");
    }
    exit();

} else {
    header("location: ../profile.php");
    exit();
}
	