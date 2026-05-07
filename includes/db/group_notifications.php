<?php
/* GROUP NOTIFICATIONS DATABASE LAYER

Handles CRUD operations for group chat notifications. */

require_once(dirname(__FILE__) . '/../dbh.inc.php');
require_once(dirname(__FILE__) . '/../guid-utilities.php');

//Create a new group notification
function createGroupNotificationByGuid($userGuid, $groupGuid, $type, $groupName): array
{
    //Validate GUIDs
    if (!isValidGuid($userGuid) || !isValidGuid($groupGuid)) {
        return array(false, "Invalid GUID format");
    }

    //Validate notification type
    $validTypes = ['added_to_group', 'group_deleted', 'group_deleted_account_removed', 'group_deactivated', 'group_reactivated', 'removed_from_group', 'became_admin'];
    if (!in_array($type, $validTypes)) {
        return array(false, "Invalid notification type");
    }

    $conn = getDbConnection();
    $error = "Failed to create notification. Please try again.";

    //Auto-acknowledge any existing unread notifications from the same group for this user (this ensures only the latest notification per group is shown)
    $ackSql = "UPDATE group_notifications
               SET is_acknowledged = 1, acknowledged_at = NOW()
               WHERE user_guid = uuid_to_bin(?, true)
               AND group_guid = uuid_to_bin(?, true)
               AND is_acknowledged = 0";

    $ackStmt = mysqli_stmt_init($conn);
    if (mysqli_stmt_prepare($ackStmt, $ackSql)) {
        mysqli_stmt_bind_param($ackStmt, "ss", $userGuid, $groupGuid);
        mysqli_stmt_execute($ackStmt);
        $acknowledgedCount = mysqli_stmt_affected_rows($ackStmt);
        if ($acknowledgedCount > 0) {
            app_log("Auto-acknowledged $acknowledgedCount old notification(s) for user $userGuid from group $groupGuid");
        }
        mysqli_stmt_close($ackStmt);
    }

    //Generate a new GUID for the notification
    $notificationGuid = generateGuid();

    $sql = "INSERT INTO group_notifications (notification_guid, user_guid, group_guid, notification_type, group_name, is_acknowledged, created_at)
            VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), uuid_to_bin(?, true), ?, ?, 0, NOW())";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "sssss", $notificationGuid, $userGuid, $groupGuid, $type, $groupName);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_close($stmt);

    return array([
        'notification_guid' => $notificationGuid,
        'user_guid' => $userGuid,
        'group_guid' => $groupGuid,
        'notification_type' => $type,
        'group_name' => $groupName
    ], "");
}

//Get all pending (unacknowledged) group notifications for a user
function getPendingGroupNotificationsByGuid($userGuid): array
{
    if (!isValidGuid($userGuid)) {
        return array(false, "Invalid user GUID format");
    }

    $conn = getDbConnection();
    $error = "Failed to retrieve notifications. Please try again.";

    $sql = "SELECT bin_to_uuid(gn.notification_guid, true) as notification_guid,
                   bin_to_uuid(gn.user_guid, true) as user_guid,
                   bin_to_uuid(gn.group_guid, true) as group_guid,
                   gn.notification_type,
                   gn.group_name,
                   gn.is_acknowledged,
                   gn.created_at,
                   gc.is_active as group_is_active
            FROM group_notifications gn
            LEFT JOIN group_chats gc ON gn.group_guid = gc.group_guid
            WHERE gn.user_guid = uuid_to_bin(?, true)
            AND gn.is_acknowledged = 0
            ORDER BY gn.created_at DESC";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "s", $userGuid);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return array($notifications, "");
}

//Acknowledge (dismiss) a group notification
function acknowledgeGroupNotificationByGuid($notificationGuid, $userGuid): array
{
    if (!isValidGuid($notificationGuid) || !isValidGuid($userGuid)) {
        return array(false, "Invalid GUID format");
    }

    $conn = getDbConnection();
    $error = "Failed to acknowledge notification. Please try again.";

    //Update only if the notification belongs to the user
    $sql = "UPDATE group_notifications
            SET is_acknowledged = 1, acknowledged_at = NOW()
            WHERE notification_guid = uuid_to_bin(?, true)
            AND user_guid = uuid_to_bin(?, true)
            AND is_acknowledged = 0";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "ss", $notificationGuid, $userGuid);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affectedRows === 0) {
        return array(false, "Notification not found or already acknowledged");
    }

    return array(true, "");
}

//Get count of pending group notifications for a user
function getGroupNotificationCountByGuid($userGuid): int
{
    if (!isValidGuid($userGuid)) {
        return 0;
    }

    $conn = getDbConnection();

    $sql = "SELECT COUNT(*) as count
            FROM group_notifications
            WHERE user_guid = uuid_to_bin(?, true)
            AND is_acknowledged = 0";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return 0;
    }

    mysqli_stmt_bind_param($stmt, "s", $userGuid);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }

    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? (int)$row['count'] : 0;
}

//Get a specific notification
function getGroupNotificationByGuid($notificationGuid): array
{
    if (!isValidGuid($notificationGuid)) {
        return array(false, "Invalid notification GUID format");
    }

    $conn = getDbConnection();
    $error = "Failed to retrieve notification. Please try again.";

    $sql = "SELECT bin_to_uuid(notification_guid, true) as notification_guid,
                   bin_to_uuid(user_guid, true) as user_guid,
                   bin_to_uuid(group_guid, true) as group_guid,
                   notification_type,
                   group_name,
                   is_acknowledged,
                   created_at,
                   acknowledged_at
            FROM group_notifications
            WHERE notification_guid = uuid_to_bin(?, true)";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "s", $notificationGuid);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $notification = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$notification) {
        return array(false, "Notification not found");
    }

    return array($notification, "");
}

//Acknowledge all pending group notifications for a specific group and user (used when user interacts with a group)
function acknowledgeGroupNotificationsByGroupGuid($groupGuid, $userGuid, $notificationTypes = []): array
{
    if (!isValidGuid($groupGuid) || !isValidGuid($userGuid)) {
        return array([], "Invalid GUID format");
    }

    $conn = getDbConnection();
    $error = "Failed to acknowledge notifications. Please try again.";

    //Get the notification GUIDs that will be acknowledged
    $selectSql = "SELECT bin_to_uuid(notification_guid, true) as notification_guid,
                         notification_type,
                         bin_to_uuid(group_guid, true) as group_guid,
                         group_name
                  FROM group_notifications
                  WHERE user_guid = uuid_to_bin(?, true)
                  AND group_guid = uuid_to_bin(?, true)
                  AND is_acknowledged = 0";

    //Add type filter if specified
    if (!empty($notificationTypes)) {
        $placeholders = implode(',', array_fill(0, count($notificationTypes), '?'));
        $selectSql .= " AND notification_type IN ($placeholders)";
    }

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $selectSql)) {
        mysqli_stmt_close($stmt);
        return array([], $error);
    }

    //Bind parameters
    if (!empty($notificationTypes)) {
        $types = "ss" . str_repeat("s", count($notificationTypes));
        $params = array_merge([$userGuid, $groupGuid], $notificationTypes);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    } else {
        mysqli_stmt_bind_param($stmt, "ss", $userGuid, $groupGuid);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array([], $error);
    }

    $result = mysqli_stmt_get_result($stmt);
    $notifications = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    //If no notifications to acknowledge, return empty array
    if (empty($notifications)) {
        return array([], "");
    }

    //Now acknowledge all matching notifications
    $updateSql = "UPDATE group_notifications
                  SET is_acknowledged = 1, acknowledged_at = NOW()
                  WHERE user_guid = uuid_to_bin(?, true)
                  AND group_guid = uuid_to_bin(?, true)
                  AND is_acknowledged = 0";

    // Add type filter if specified
    if (!empty($notificationTypes)) {
        $placeholders = implode(',', array_fill(0, count($notificationTypes), '?'));
        $updateSql .= " AND notification_type IN ($placeholders)";
    }

    $updateStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($updateStmt, $updateSql)) {
        mysqli_stmt_close($updateStmt);
        return array([], $error);
    }

    //Bind parameters
    if (!empty($notificationTypes)) {
        $types = "ss" . str_repeat("s", count($notificationTypes));
        $params = array_merge([$userGuid, $groupGuid], $notificationTypes);
        mysqli_stmt_bind_param($updateStmt, $types, ...$params);
    } else {
        mysqli_stmt_bind_param($updateStmt, "ss", $userGuid, $groupGuid);
    }

    if (!mysqli_stmt_execute($updateStmt)) {
        mysqli_stmt_close($updateStmt);
        return array([], $error);
    }

    $affectedRows = mysqli_stmt_affected_rows($updateStmt);
    mysqli_stmt_close($updateStmt);

    if ($affectedRows > 0) {
        app_log("Acknowledged $affectedRows notification(s) for user $userGuid from group $groupGuid");
    }

    return array($notifications, "");
}

//Check if user is a member of the group and the group is active (for Chat button visibility)
function canUserAccessGroupChatByGuid($groupGuid, $userGuid): bool
{
    if (!isValidGuid($groupGuid) || !isValidGuid($userGuid)) {
        return false;
    }

    $conn = getDbConnection();

    //Check if user is a member AND group is active
    $sql = "SELECT 1 FROM group_members gm
            INNER JOIN group_chats gc ON gm.group_guid = gc.group_guid
            WHERE gm.group_guid = uuid_to_bin(?, true)
            AND gm.user_guid = uuid_to_bin(?, true)
            AND gc.is_active = 1";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_bind_param($stmt, "ss", $groupGuid, $userGuid);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    $canAccess = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $canAccess;
}