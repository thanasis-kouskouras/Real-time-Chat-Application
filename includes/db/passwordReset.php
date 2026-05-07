<?php
require_once(dirname(__FILE__) . '/../dbh.inc.php');

function deletePasswordReset($email): array
{
    $conn = getDbConnection();
    $error = "Failed to delete password reset. Please try again.";
    $sql = "DELETE FROM passwordReset WHERE email=?;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $email);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

/* Returns [$selector, $rawToken, ""] on success, so the caller can build the reset URL.
The raw token is never stored (only its bcrypt hash is stored in DB). */
function createPasswordReset($userEmail): array
{
    $conn = getDbConnection();
    $error = "Failed to create password reset. Please try again.";
    $selector = bin2hex(random_bytes(16)); //32 hex chars (128 bits of randomness)
    $token = random_bytes(32);             //32 bytes raw (256 bits)
    $expires = intval(date("U")) + PASSWORD_RESET_TIME_INTERVAL;

    $sql = "INSERT INTO passwordReset (email, selector, token, expires) VALUES (?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, false, $error);
    } else {
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        mysqli_stmt_bind_param($stmt, "ssss", $userEmail, $selector, $hashedToken, $expires);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, false, $error);
        }
    }
    mysqli_stmt_close($stmt);
    return array($selector, $token, "");
}

function getPasswordResetBySelectorAndDate($selector, $timestamp): array
{
    $conn = getDbConnection();
    $error = "Failed to get password reset. Please try again.";
    $sql = "SELECT * FROM passwordReset where selector = ? and expires >= ?;";
    $stmt = mysqli_stmt_init($conn);
    $time = intval($timestamp);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "si", $selector, $time);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return [$row, ""];
}