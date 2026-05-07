<?php

function createUserTokenByGuid(string $user_guid, string $selector, string $hashed_validator, string $expiry): array
{
    $conn = getDbConnection();
    $error = "Failed to create user remember me token. Please try again.";
    
    $sql = 'INSERT INTO usertokens(user_guid, selector, hashed_validator, expiry)
            VALUES(uuid_to_bin(?, true), ?, ?, ?)';
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ssss", $user_guid, $selector, $hashed_validator, $expiry);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return [true, ""];
}


function getUserTokenBySelector(string $selector): array
{
    $conn = getDbConnection();
    $error = "Failed to get user remember me token. Please try again.";
    $sql = 'SELECT id, selector, hashed_validator, user_guid, expiry
                FROM usertokens
                WHERE selector = ? AND
                    expiry >= now()
                LIMIT 1';

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
    $conn = getDbConnection();
    $error = "Failed to get user by remember me token. Please try again.";
    $tokens = parse_token($token);
    if (!$tokens) {
        return null;
    }
    $selector = $tokens[0];
    $sql = "SELECT u.user_username, u.user_password,
       u.user_email, u.user_status, u.user_email_verified,
       u.user_created_date, u.user_verification_token,
       u.user_role, u.user_banned,
       bin_to_uuid(u.user_guid, true) as user_guid
            FROM users as u
            INNER JOIN usertokens ut ON ut.user_guid = u.user_guid
            WHERE ut.selector = ? AND
                ut.expiry > now()
            LIMIT 1";

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


function deleteUserTokenByGuid(string $user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to delete remember me token. Please try again.";
    $sql = 'DELETE FROM usertokens WHERE user_guid = uuid_to_bin(?, true)';
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return [true, ""];
}