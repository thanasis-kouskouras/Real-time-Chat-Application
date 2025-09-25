<?php

include(dirname(__FILE__) . '/../dbh.inc.php');
function createUsers($username, $email, $pwd): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed creating new user. Please try again.";
    $sql = "INSERT INTO users (usersUsername, usersEmail,
    usersPassword, usersCreatedDate, usersVerificationToken, usersVerificationTokenExpireDate) VALUES (?, ?, ?, ?,?,?);";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $expires = intval(date("U")) + VERIFICATION_TIME_INTERVAL;
    $token = md5(uniqid());
    $hashedPassword = password_hash($pwd, PASSWORD_DEFAULT);
    $date = date('Y/m/d H:i:s');
    mysqli_stmt_bind_param($stmt, "ssssss",
        $username, $email, $hashedPassword, $date, $token, $expires);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array($token, "");
}

function reset_verification_token($email): array
{

    $conn = $GLOBALS['_mc'];
    list($user, $error) = getUserByEmail($email);
    $error = "Failed to find user. Please try again.";
    $expires = intval(date("U")) + VERIFICATION_TIME_INTERVAL;
    $sql = "UPDATE users SET
                   usersVerificationTokenExpireDate = ? WHERE  UsersID = ?;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "si", $expires, $user['UsersID']);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return [true, ""];
}

function searchUser($searchString): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed searching users. Please try again.";
    $search = escapeString($searchString);
    $sql = "SELECT UsersID, usersUsername, usersPassword,
       		usersEmail, usersStatus, usersEmailVerified,
       		usersCreatedDate, usersVerificationToken,
       		bin_to_uuid(usersguid, true) as usersGuid
        	FROM users  
            WHERE usersUsername LIKE concat('%',?,'%') 
            and usersAreDeleted != 1 
            ORDER BY usersStatus DESC, usersUsername ASC";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $search);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function searchUserExceptMe($searchString, $id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed searching users. Please try again.";
    $search = escapeString($searchString);
    
    // First get all potential matches
    $sql = "SELECT UsersID, usersUsername, usersEmail, usersStatus, usersEmailVerified, 
        	usersCreatedDate, bin_to_uuid(usersguid, true) as usersGuid
        	FROM users  
            WHERE usersUsername LIKE concat('%',?,'%') 
            AND UsersID != ? 
            and usersAreDeleted != 1 
            and usersEmailVerified = 'True' 
            ORDER BY usersUsername ASC";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "si", $search, $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $allResults = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    // Filter results based on user preferences
    $filteredResults = [];

    foreach ($allResults as $user) {
        // Check if user should be shown in search based on their settings
        if (shouldShowInSearch($user, $search)) {
            $filteredResults[] = $user;
        }
    }
    
    return array($filteredResults, "");
}

function validateEmailToken($token): array
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed validation of email token. Please try again.";
    $sql = "SELECT * FROM users WHERE usersVerificationToken = ? and usersAreDeleted != 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $token);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    $return = false;
    if ($stmt->num_rows() > 0)
        $return = true;
    return array($return, "");
}

function enableUser($id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed enabling user. Please try again.";
    $sql = "Update  users SET usersEmailVerified = ?  WHERE UsersID = ? and usersAreDeleted != 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $var = "True";
    mysqli_stmt_bind_param($stmt, "si", $var, $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function updateUserStatus($id, $status = 'Active'): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed enabling user. Please try again.";
    $sql = "Update  users SET usersStatus = ?  WHERE UsersID = ? and usersAreDeleted != 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getUserByEmail($email): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to find user. Please try again.";
    $sql = "SELECT UsersID, usersUsername, usersPassword, usersEmail, usersStatus, usersEmailVerified, usersCreatedDate, usersVerificationToken, bin_to_uuid(usersguid, true) as usersGuid FROM users WHERE usersEmail = ? and usersAreDeleted != 1;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $email);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getUserById($id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to find user. Please try again.";
    $sql = "SELECT UsersID, usersUsername, usersPassword, usersEmail, usersStatus, usersEmailVerified, usersCreatedDate, usersVerificationToken, bin_to_uuid(usersguid, true) as usersGuid FROM users WHERE UsersID = ? and usersAreDeleted != 1;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");
}


function getUserByGuid($id): bool|array|null
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed to find user. Please try again.";
    $sql = "SELECT UsersID, usersUsername, usersPassword, usersEmail, usersStatus, usersEmailVerified, usersCreatedDate, usersVerificationToken, bin_to_uuid(usersguid, true) as usersGuid FROM users WHERE usersGuid = uuid_to_bin(?, true) and usersAreDeleted != 1;";

    $stmt = mysqli_stmt_init($conn);
    mysqli_stmt_prepare($stmt, $sql);
    mysqli_stmt_bind_param($stmt, "s", $id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);

    return array($row, "");
}

function getUserByGuidOrId($id): bool|array|null
{
    if (is_int($id)) {
        list($user, $error) = getUserById($id);
    } else
        list($user, $error) = getUserByGuid($id);
    return array($user, $error);
}

function changePassword($newPassword, $userid): array
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed to change password for user. Please try again.";
    $sql = "UPDATE users SET usersPassword = ? WHERE UsersID = ? and usersAreDeleted != 1;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        return array(false, $error);
    }
    $newhashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    mysqli_stmt_bind_param($stmt, "si", $newhashedPassword, $userid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}


function changeUsername_($newUsername, $userid): array
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed to change username for user. Please try again.";
    $sql = "UPDATE users SET usersUsername = ? WHERE UsersID = ? and usersAreDeleted != 1;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "si", $newUsername, $userid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function deleteUserById($userid): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to delete user. Please try again.";
    $sql = "UPDATE users SET usersAreDeleted = 1 WHERE UsersID = ?;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "i", $userid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getUserByVerificationToken($token, $checkExpiry = null): array
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed to find user. Please try again.";
    $sql = "SELECT * FROM users WHERE usersVerificationToken = ? and usersAreDeleted != 1;";

    if ($checkExpiry !== null) {
        $sql = "SELECT UsersID, usersUsername, usersPassword, usersEmail, usersStatus, usersEmailVerified, usersCreatedDate, usersVerificationToken,          bin_to_uuid(usersguid, true) as usersGuid 
        FROM users WHERE usersVerificationToken = ? and usersAreDeleted != 1
        and UNIX_TIMESTAMP() <= usersVerificationTokenExpireDate ;";
    }
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $token);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");

}

function setUsersStatusInactive(): array
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed to find user. Please try again.";
    $sql = "UPDATE `users` SET `usersStatus` = 'Offline'";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}