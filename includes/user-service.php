<?php
/* USER SERVICE FUNCTIONS

User account operations and management. */

require_once __DIR__ . '/db/users.php';
require_once __DIR__ . '/db/profileImage.php';
require_once __DIR__ . '/authentication.php';
require_once __DIR__ . '/email-service.php';

function createUser($username, $email, $password): array
{
    list($token, $error) = createUsers($username, $email, $password);
    if ($error != "")
        return array($token, $error);
    list($user, $error) = getUserByVerificationToken($token);
    if ($error != "")
        return array(false, $error);
    $user_guid = $user['user_guid'];
    list($success, $error) = createDefaultProfileImageByGuid($user_guid);
    if ($error != "")
        return array($token, $error);
    list($id, $error) = verify_email($email, $token);

    if ($error != "")
        return array($token, $error);
    return array($token, "");
}

function deleteAccountManagerByGuid($user_guid): bool
{
    $loggedOut = logout($user_guid);
    $deleted = false;
    if ($loggedOut) {
        list($deleted, $error) = deleteUserByGuid($user_guid);
        if ($error !== "") {
            setSessionError($error);
            return false;
        }
    }
    return $deleted;
}

function validateVerificationTokenByGuid($inputToken): bool
{
    list($user, $error) = getUserByVerificationToken($inputToken, true);
    if ($error !== "") {
        setSessionError($error);
    }
    if (is_array($user)) {
        list($enabled, $error) = enableUserByGuid($user['user_guid']);
        if ($error !== "") {
            setSessionError($error);
            return false;
        }
        return $enabled;
    }
    return false;
}

function shouldShowInSearchWithExactMatch($user, $search): bool
{
    //Check if this is an exact match
    $isExactMatch = (strtolower($user['user_username']) === strtolower($search));

    if (isset($user['user_guid']) && !empty($user['user_guid'])) {
        $settings = getUserSettingsByGuid($user['user_guid']);
    } else {
        //If no GUID available, return empty settings
        $settings = [];
    }
    
    $hideFromSearch = isset($settings['hide_account_from_search']) &&
        $settings['hide_account_from_search'] == 1;

    //If account is hidden and the search is not exact, don't show the user
    if ($hideFromSearch && !$isExactMatch) {
        return false;
    }

    return true;
}

function shouldSendEmailNotificationByGuid($user_guid): bool
{
    $settings = getUserSettingsByGuid($user_guid);
    return isset($settings['email_notifications']) &&
        $settings['email_notifications'] == 1;
}


function getUserSettingsByGuid($user_guid): array
{
    require_once __DIR__ . '/../config.php';
    $conn = getDbConnection();

    if (!isValidGuid($user_guid)) {
        return [];
    }

    $stmt = $conn->prepare("SELECT setting_name, setting_value FROM user_settings WHERE user_guid = uuid_to_bin(?, true)");
    $stmt->bind_param("s", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();

    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }

    return $settings;
}

function saveUserSettingsByGuid($user_guid, $settings)
{
    require_once __DIR__ . '/../config.php';
    $conn = getDbConnection();

    if (!isValidGuid($user_guid)) {
        return false;
    }

    //First delete existing settings
    $stmt = $conn->prepare("DELETE FROM user_settings WHERE user_guid = uuid_to_bin(?, true)");
    $stmt->bind_param("s", $user_guid);
    $stmt->execute();

    //Then insert new settings
    $stmt = $conn->prepare("INSERT INTO user_settings (user_guid, setting_name, setting_value) VALUES (uuid_to_bin(?, true), ?, ?)");

    foreach ($settings as $name => $value) {
        $stmt->bind_param("sss", $user_guid, $name, $value);
        $stmt->execute();
    }

    return true;
}

function shouldSendEmailFromSenderByGuid($recipientUserGuid, $senderUserGuid): bool
{
    require_once __DIR__ . '/../config.php';
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT ent.last_email_sent 
                           FROM email_notification_throttle ent 
                           WHERE ent.user_guid = uuid_to_bin(?, true) 
                           AND ent.sender_guid = uuid_to_bin(?, true)");
    $stmt->bind_param("ss", $recipientUserGuid, $senderUserGuid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    //If no record exists, email should be sent
    if ($result->num_rows === 0) {
        return true;
    }
    
    //Record exists, so email was already sent while user was offline
    return false;
}


function markEmailSentFromSenderByGuid($recipientUserGuid, $senderUserGuid): bool
{
    require_once __DIR__ . '/../config.php';
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO email_notification_throttle (user_guid, sender_guid, last_email_sent) 
                           VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), NOW()) 
                           ON DUPLICATE KEY UPDATE last_email_sent = NOW()");
    $stmt->bind_param("ss", $recipientUserGuid, $senderUserGuid);
    
    return $stmt->execute();
}


function clearEmailThrottleForUserByGuid($user_guid): bool
{
    require_once __DIR__ . '/../config.php';
    $conn = getDbConnection();

    //Clear throttle
    $stmt = $conn->prepare("DELETE FROM email_notification_throttle WHERE user_guid = uuid_to_bin(?, true)");
    $stmt->bind_param("s", $user_guid);
    
    return $stmt->execute();
}