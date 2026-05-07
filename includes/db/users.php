<?php

require_once(dirname(__FILE__) . '/../dbh.inc.php');
require_once(dirname(__FILE__) . '/../validation.php');

function createUsers($username, $email, $pwd): array
{
    $conn = getDbConnection();
    $error = "Failed creating new user. Please try again.";
    
    $sql = "INSERT INTO users (user_username, user_email, user_password, user_created_date, user_verification_token, user_verification_token_expire_date, user_guid) VALUES (?, ?, ?, ?, ?, ?, uuid_to_bin(uuid(), true));";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $expires = intval(date("U")) + VERIFICATION_TIME_INTERVAL;
    $token = bin2hex(random_bytes(32));
    $hashedPassword = password_hash($pwd, PASSWORD_DEFAULT);
    $date = date('Y/m/d H:i:s');
    
    mysqli_stmt_bind_param($stmt, "ssssss", $username, $email, $hashedPassword, $date, $token, $expires);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array($token, "");
}

function reset_verification_token($email): array
{

    $conn = getDbConnection();
    list($user, $error) = getUserByEmail($email);
    if ($error !== "" || !is_array($user)) {
        return array(false, "Failed to find user. Please try again.");
    }
    $expires = intval(date("U")) + VERIFICATION_TIME_INTERVAL;
    
    $sql = "UPDATE users SET user_verification_token_expire_date = ? WHERE user_guid = uuid_to_bin(?, true);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ss", $expires, $user['user_guid']);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return [true, ""];
}


function searchUserExceptMeByGuid($searchString, $user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed searching users. Please try again.";

    $sql = "SELECT user_username, user_email, user_status, user_email_verified,
        	user_created_date, bin_to_uuid(user_guid, true) as user_guid
        	FROM users  WHERE user_username LIKE concat('%',?,'%') AND user_guid != uuid_to_bin(?, true) and user_is_deleted != 1 and user_email_verified = 'True' ORDER BY user_username ASC";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ss", $searchString, $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $allResults = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    //Filter results based on user preferences
    $filteredResults = [];

    foreach ($allResults as $user) {
        // Check if user should be shown in search based on their settings
        if (shouldShowInSearchWithExactMatch($user, $searchString)) {
            $filteredResults[] = $user;
        }
    }
    
    return array($filteredResults, "");
}

function enableUserByGuid($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed enabling user. Please try again.";
    
    $sql = "Update users SET user_email_verified = ? WHERE user_guid = uuid_to_bin(?, true) and user_is_deleted != 1 ";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $var = "True";
    mysqli_stmt_bind_param($stmt, "ss", $var, $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function updateUserStatusByGuid($user_guid, $status = 'Active'): array
{
    $conn = getDbConnection();
    $error = "Failed updating user status. Please try again.";
    
    $sql = "Update users SET user_status = ? WHERE user_guid = uuid_to_bin(?, true) and user_is_deleted != 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ss", $status, $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getUserByEmail($email): array
{
    $conn = getDbConnection();
    $error = "Failed to find user. Please try again.";
    
    $sql = "SELECT user_username, user_password,
       user_email, user_role, user_status, user_email_verified,
       user_created_date, user_verification_token, user_banned,
       bin_to_uuid(user_guid, true) as user_guid FROM users WHERE user_email = ? and user_is_deleted != 1;";
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




function getUserByGuid($id): bool|array|null
{

    $conn = getDbConnection();
    $error = "Failed to find user. Please try again.";
    
    $sql = "SELECT user_username, user_password,
       user_email, user_role, user_status, user_email_verified,
       user_created_date, user_verification_token,
       bin_to_uuid(user_guid, true) as user_guid
        FROM users WHERE user_guid = uuid_to_bin(?, true) and user_is_deleted != 1;";
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

function changePasswordByGuid($newPassword, $user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to change password for user. Please try again.";
    
    $sql = "UPDATE users SET user_password = ? WHERE user_guid = uuid_to_bin(?, true) and user_is_deleted != 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $newhashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    mysqli_stmt_bind_param($stmt, "ss", $newhashedPassword, $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function changeUsernameByGuid($newUsername, $user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to change username for user. Please try again.";
    
    $sql = "UPDATE users SET user_username = ? WHERE user_guid = uuid_to_bin(?, true) and user_is_deleted != 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ss", $newUsername, $user_guid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function deleteUserByGuid($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to delete user. Please try again.";
    
    //Update status to Offline before deletion
    list($statusSuccess, $statusError) = updateUserStatusByGuid($user_guid, 'Offline');
    if ($statusError !== "") {
        app_log("Warning: Failed to update user status to Offline during deletion: $statusError");
        //Continue with deletion even if status update fails
    }
    
    //Mark user as deleted (soft delete)
    $sql = "UPDATE users SET user_is_deleted = 1 WHERE user_guid = uuid_to_bin(?, true);";
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
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getUserByVerificationToken($token, $checkExpiry = null): array
{

    $conn = getDbConnection();
    $error = "Failed to find user. Please try again.";
    
    if ($checkExpiry !== null) {
        $sql = "SELECT user_username, user_password,
        user_email, user_role, user_status, user_email_verified,
        user_created_date, user_verification_token,
        bin_to_uuid(user_guid, true) as user_guid FROM users WHERE user_verification_token = ? and user_is_deleted != 1
        and UNIX_TIMESTAMP() <= user_verification_token_expire_date ;";
    } else {
        $sql = "SELECT user_username, user_password,
        user_email, user_role, user_status, user_email_verified,
        user_created_date, user_verification_token, user_banned,
        bin_to_uuid(user_guid, true) as user_guid FROM users WHERE user_verification_token = ? and user_is_deleted != 1;";
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

    $conn = getDbConnection();
    $error = "Failed to update user statuses. Please try again.";

    $sql = "UPDATE `users` SET `user_status` = 'Offline'";
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


function canBanUserByGuid($adminGuid, $targetUserGuid): bool
{
    //Admin can not ban themselves
    return $adminGuid !== $targetUserGuid;
}

function getAllUsers(): array
{
    $conn = getDbConnection();
    $error = "Failed to fetch users. Please try again.";
    
    $sql = "SELECT u.user_username, u.user_email, u.user_role, 
                   u.user_status, u.user_banned, u.user_created_date,
                   bin_to_uuid(u.user_guid, true) as user_guid
            FROM users u
            WHERE u.user_is_deleted != 1 
            ORDER BY u.user_created_date DESC";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $users = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    return array($users, "");
}


function banUserByGuid($user_guid): array
{
    if (!$user_guid || !is_string($user_guid)) {
        return array(false, "Invalid user GUID.");
    }
    
    list($user, $error) = getUserByGuid($user_guid);
    if ($error !== "" || !is_array($user)) {
        return array(false, "User not found.");
    }
    
    $conn = getDbConnection();
    $error = "Failed to ban user. Please try again.";
    
    $sql = "UPDATE users SET user_banned = 1 WHERE user_guid = uuid_to_bin(?, true) AND user_is_deleted != 1";
    
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
    
    mysqli_stmt_close($stmt);
    
    //Send WebSocket notification to force-disconnect the banned user
    require_once dirname(__FILE__) . '/../websocket_notifications.php';
    $notificationData = [
        'type' => 'user_banned',
        'action' => 'user_banned',
        'message' => 'Your account has been banned.',
        'force_disconnect' => true,
        'status' => false,
        'loggedIn' => false
    ];
    sendToUserByGuid($user_guid, $notificationData);
    
    return array(true, "");
}


function unbanUserByGuid($user_guid): array
{
    if (!$user_guid || !is_string($user_guid)) {
        return array(false, "Invalid user GUID.");
    }
    
    list($user, $error) = getUserByGuid($user_guid);
    if ($error !== "" || !is_array($user)) {
        return array(false, "User not found.");
    }
    
    $conn = getDbConnection();
    $error = "Failed to unban user. Please try again.";
    
    $sql = "UPDATE users SET user_banned = 0 WHERE user_guid = uuid_to_bin(?, true) AND user_is_deleted != 1";
    
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
    
    mysqli_stmt_close($stmt);
    return array(true, "");
}

//Get all admin users (for broadcasting status updates to User Management page)
function getAdminUserGuids(): array
{
    $conn = getDbConnection();
    $error = "Failed to fetch admin users. Please try again.";

    $sql = "SELECT bin_to_uuid(user_guid, true) as user_guid
            FROM users
            WHERE user_role = 'admin'
              AND user_is_deleted != 1
              AND user_banned != 1";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array([], $error);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array([], $error);
    }

    $resultData = mysqli_stmt_get_result($stmt);
    $admins = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $adminGuids = array_map(function($admin) {
        return $admin['user_guid'];
    }, $admins);

    return array($adminGuids, "");
}

//Used by the admin User Management page — bypasses privacy settings to show all users
function searchUsers($searchTerm): array
{
    $conn = getDbConnection();
    $error = "Failed to search users. Please try again.";
    
    $sql = "SELECT u.user_username, u.user_email, u.user_role, 
                   u.user_status, u.user_banned, u.user_created_date,
                   bin_to_uuid(u.user_guid, true) as user_guid
            FROM users u
            WHERE (u.user_username LIKE CONCAT('%', ?, '%') 
                   OR u.user_email LIKE CONCAT('%', ?, '%'))
                  AND u.user_is_deleted != 1 
            ORDER BY u.user_created_date DESC";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "ss", $searchTerm, $searchTerm);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $users = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    return array($users, "");
}