<?php

// remember me tokens
function createUserToken(int $user_id, string $selector, string $hashed_validator, string $expiry): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to create user remember me token. Please try again.";
    $sql = 'INSERT INTO usertokens(user_id, selector, hashed_validator, expiry)
            VALUES(?,?,?,?)';

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $selector, $hashed_validator, $expiry);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return [true, ""];
}


function getUserTokenBySelector(string $selector): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get user remember me token. Please try again.";
    $sql = 'SELECT id, selector, hashed_validator, user_id, expiry FROM usertokens
    WHERE selector = ? AND expiry >= now() LIMIT 1';

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $selector);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return [$row, ""];
}

function getUserByToken(string $token): ?array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get user by remember me token. Please try again.";
    $tokens = parse_token($token);
    if (!$tokens) {
        return null;
    }
    $selector = $tokens[0];
    $sql = "SELECT u.UsersID, u.usersUsername, u.usersPassword, u.usersEmail,           u.usersStatus, u.usersEmailVerified, u.usersCreatedDate, u.usersVerificationToken,
    bin_to_uuid(u.usersguid, true) as usersGuid
    FROM users as u INNER JOIN usertokens ON user_id = u.UsersID WHERE selector = ?    AND expiry > now() LIMIT 1";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $selector);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return [$row,""];
}

function deleteUserToken(int $user_id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to delete remember me token. Please try again.";
    $sql = 'DELETE FROM usertokens WHERE user_id = ?';
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return [true, ""];
}