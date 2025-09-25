<?php

require_once 'functions.inc.php';
if (isset($_POST["forgot-password-reset-submit"])) {

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $selector = filter_var($_POST["selector"], FILTER_SANITIZE_STRING);
    $validator = filter_var($_POST["validator"], FILTER_SANITIZE_STRING);
    $password = filter_var($_POST["password"], FILTER_SANITIZE_STRING);
    $passwordRepeat = filter_var($_POST["confirm-password"], FILTER_SANITIZE_STRING);

    if (empty($password) !== false || empty($passwordRepeat) !== false) {
        header("location: ../forgot-password-reset.php?error=emptyinput&selector=$selector&validator=$validator");
        exit();
    } else if (invalidPassword($password) !== false) {
        header("location: ../forgot-password-reset.php?error=invalidPwd&selector=$selector&validator=$validator");
        exit();
    } else if ($password != $passwordRepeat) {
        header("location: ../forgot-password-reset.php?error=passwordsdontmatch&selector=$selector&validator=$validator");
        exit();
    }

    $currentDate = date("U");
    $tokenBin = hex2bin($validator);
    list($row, $error) = getPasswordResetBySelectorAndDate($selector, $tokenBin, $currentDate);
    if ($error !== "") {
        header("location: ../login.php?error=stmtfailed");
        exit();
    }
    if (!is_array($row) || $row == null) {
        header("location: ../forgot-password-reset.php?error=linkExpired");
        exit();
    } else {
        $tokenCheck = $tokenBin === $row["Token"];
        if ($tokenCheck === false) {
            header("location: ../login.php?error=stmtfailed");
        } else {
            $tokenEmail = $row["Email"];

            list($rowData, $error) = getUserByEmail($tokenEmail);
            if ($error !== "") {
                setSessionError($error);
                header("location: ../forgot-password.php");
                exit();
            }
            list($success, $error) = changePassword($password, $rowData['UsersID']);
            if ($error !== "") {
                setSessionError($error);
                header("location: ../forgot-password.php");
                exit();
            }
            if ($success) {
                list($success, $error) = deletePasswordReset($tokenEmail);
                if ($success){
                    header("location: ../login.php?error=PasswordResetOk");
                }
                exit();
            }
            header("location: ../forgot-password.php");
            setSessionError("An error occurred. Please try again.");
            exit();
        }
    }
    exit();
} else {
    header("location: ../login.php");
    exit();
}