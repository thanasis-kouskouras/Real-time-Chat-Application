<?php

if (isset($_POST["login-submit"])) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'functions.inc.php';
    $email = filter_var($_POST["email"], FILTER_SANITIZE_EMAIL);
    $password = filter_var($_POST["password"], FILTER_SANITIZE_STRING);
    $remember_me = isset($_POST["remember_me"]);
    $jsonRequest = isset($_POST["jsonRequest"]);
    $jsonData = array();

    if (emptyInputLogin($email, $password) !== false) {
        header("location: ../login.php?error=emptyInputLogin");
        exit();
    }
    list($user, $error) = core_login($email, $password, $remember_me);
    if ($jsonRequest) { // api log in
        $jsonData['user'] = $user;
        if($error !== "") {
            $jsonData['message'] = $error;
            $jsonData['loggedIn'] = "false";
        }
        else if (is_array($user)) {
            $jsonData['message'] = "success";
            $jsonData['loggedIn'] = "true";
        } else {
            $jsonData['message'] = "Wrong Email/Password.Please try again.";
            $jsonData['loggedIn'] = "false";
        }
        echo json_encode($jsonData);
    } else {// normal login
        if ($error !== "") {
            setSessionError($error);
            header("location: ../login.php?email=$email");
            exit();
        }
        if (is_array($user)) {
            // Login successful - check for redirect URL
            $redirect_url = 'index.php'; // keep original default redirect
            
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect_url = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']); // Clear it after use
            }
            
            // Don't add ../ if it's just a filename (no path separators)
            if (strpos($redirect_url, '/') === false) {
                header("location: ../" . $redirect_url);
            } else {
                // If it contains slashes, it might be a relative path already
                header("location: " . $redirect_url);
            }
        } else {
            header("location: ../login.php?error=wrongPassword&email=$email");
        }
    }
} else {
    header("location: ../login.php");
}
exit();