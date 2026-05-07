<?php
/* DELETE ACCOUNT ENDPOINT

Permanently deletes the user's account. */

require_once __DIR__ . '/../../includes/db/group_chats.php';
require_once __DIR__ . '/../../includes/db/group_notifications.php';

//Rate limit (3 deletion attempts per day per user)
checkRateLimit($user_guid, 'delete_account', 3, 86400);

//Get user's friends before deletion
list($friends, $error) = getFriendsByGuid($user_guid);
if ($error !== "") {
    app_log("Failed to get friends for account deletion: $error");
    $friends = [];
}

//Get all active groups where this user is the current admin and their members before deletion
$groupMembersToNotify = [];
list($adminGroups, $groupsError) = getGroupsWhereUserIsAdminByGuid($user_guid);
if ($groupsError === "" && is_array($adminGroups)) {
    foreach ($adminGroups as $group) {
        list($members, $membersError) = getGroupMembersByGuid($group['group_guid']);
        if ($membersError === "" && is_array($members)) {
            $groupMembersToNotify[$group['group_guid']] = [
                'group_name' => $group['group_name'],
                'members' => $members
            ];
        }
    }
}

//Delete all groups where this user is the current admin
list($groupsDeleted, $deletedGroups, $deleteGroupsError) = deleteAllGroupsWhereUserIsAdminByGuid($user_guid);
if (!$groupsDeleted && $deleteGroupsError !== "") {
    app_log("Failed to delete groups for account deletion: $deleteGroupsError");
}

//Delete the account
$result = deleteAccountManagerByGuid($user_guid);

if (!$result) {
    sendError("Failed to delete account. Please try again.", 500);
}

//Notify all friends that this user deleted its account
if (is_array($friends) && count($friends) > 0) {
    $notificationData = [
        'type' => 'account_deleted',
        'action' => 'deleteAccount',
        'friendUserGuid' => $user_guid,
        'status' => true,
        'loggedIn' => true
    ];

    foreach ($friends as $friend) {
        $friendGuid = $friend['user_guid'] ?? null;

        //Only send notification if have a valid friend GUID
        if ($friendGuid && isValidGuid($friendGuid)) {
            sendToUserByGuid($friendGuid, $notificationData);
        } else {
            app_log("Invalid or missing friend GUID in delete account notification: " . json_encode($friend));
        }
    }
}

//Notify all group members that the groups created by this user have been deleted
foreach ($groupMembersToNotify as $groupGuid => $groupData) {
    $groupNotificationData = [
        'type' => 'group_deleted',
        'action' => 'group_deleted',
        'group_guid' => $groupGuid,
        'group_name' => $groupData['group_name'],
        'deleted_at' => date('Y-m-d H:i:s'),
        'status' => true,
        'loggedIn' => true
    ];

    foreach ($groupData['members'] as $member) {
        $memberGuid = $member['user_guid'] ?? null;

        //Don't notify the user who is deleting its own account
        if ($memberGuid && $memberGuid !== $user_guid && isValidGuid($memberGuid)) {
            //Real-time event to remove group from Groups/Messages/Chat pages
            sendToUserByGuid($memberGuid, $groupNotificationData);

            //Persistent notification for the Notifications page
            list($notificationResult) = createGroupNotificationByGuid(
                $memberGuid,
                $groupGuid,
                'group_deleted_account_removed',
                $groupData['group_name']
            );

            //Real-time update of the Notifications page for this user
            if ($notificationResult && function_exists('sendGroupNotificationCreatedByGuid')) {
                sendGroupNotificationCreatedByGuid(
                    $memberGuid,
                    $notificationResult['notification_guid'],
                    $groupGuid,
                    $groupData['group_name'],
                    'group_deleted_account_removed',
                    false
                );
            }
        }
    }
}

//Clear cookies
$_isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
setcookie('jwt', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $_isSecure, 'httponly' => true, 'samesite' => 'Lax']);
setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $_isSecure, 'httponly' => true, 'samesite' => 'Lax']);

sendResponse(true, null, 'Account deleted successfully', 200);