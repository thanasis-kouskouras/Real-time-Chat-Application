<?php
require_once "includes/functions.inc.php";
require_once __DIR__ . '/api/api-response.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['code'])) {
    /* Rate-limit code-submission attempts per IP (20 / 15 min). 
    Protects the GET ?code= path from brute-force enumeration of verification tokens. */
    $rateLimitIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit($rateLimitIp, 'verify_email_attempt', 20, 900);

    $isValid = validateVerificationTokenByGuid(sanitizeString($_GET['code']));

    if ($isValid) {
        $_SESSION['message'] = 'Your email has been verified, now you can login into EasyTalk chat Application';
    } else {
        if (!isset($_SESSION['error']))
            $_SESSION['error'] = 'Your email verification has error. Please try again.';
    }
    header('location:login.php');
    exit();
} else if (isset($_POST["email"])) {

    $email = sanitizeEmail($_POST["email"]);

    //Rate limit (3 verification email resends per hour per email address)
    if (!empty($email)) {
        checkRateLimit($email, 'verify_resend', 3, 3600);
    }

    $emailExists = emailExists($email);
    if ($email !== false && $emailExists) {
        $isVerified = isVerified($email);
        if ($isVerified) {
            setSessionError("Email already verified!");
            header('location:login.php');
            exit();
        } else {
            //Send a new verification link
            list($success, $error) = generate_verification_link($email);
            if (!$success) {
                setSessionError($error ?: 'Failed to send verification email. Please try again.');
            }
            header('location:login.php');
            exit();
        }
    } else {
        setSessionError("Email not correct to generate verification link.");
        header('location:login.php');
        exit();

    }
}