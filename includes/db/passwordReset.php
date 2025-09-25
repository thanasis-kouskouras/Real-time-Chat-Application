<?php
include(dirname(__FILE__) . '/../dbh.inc.php');
function deletePasswordReset($email): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to delete password reset. Please try again.";
    $sql = "DELETE FROM passwordReset WHERE Email=?;";
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


/**
 * @throws Exception
 */
function createPasswordReset($userEmail): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to delete password reset. Please try again.";
    $selector = bin2hex(random_bytes(8));
    $token = random_bytes(32);
    $expires = intval(date("U")) + PASSWORD_RESET_TIME_INTERVAL;

    $sql = "INSERT INTO passwordReset (Email, Selector, Token, Expires) VALUES (?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        mysqli_stmt_bind_param($stmt, "ssss", $userEmail, $selector, $hashedToken, $expires);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getPasswordReset($userEmail): array|null|bool
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get password reset. Please try again.";
    $sql = "SELECT * FROM passwordReset where Email = ?;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $userEmail);
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

/**
 * @param $selector
 * @param $validator
 * @return array|bool|null
 */
function getPasswordResetBySelector($selector, $validator): array|null|bool
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get password reset. Please try again.";
    $sql = "SELECT * FROM passwordReset where Selector = ? and Token = ?;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $selector, $validator);
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

function getPasswordResetBySelectorAndDate($selector, $validator, $timestamp): array|null|bool
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get password reset. Please try again.";
    $sql = "SELECT * FROM passwordReset where Selector = ? and Token = ? and Expires >= ?;";
    $stmt = mysqli_stmt_init($conn);
    $time = intval($timestamp);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "ssi", $selector, $validator, $time);
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