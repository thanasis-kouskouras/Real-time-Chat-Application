<?php
/* DELETE GROUP ENDPOINT

Handles POST requests for permanently deleting a group.
Requires admin role in the group. */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');
require_once(dirname(__FILE__) . '/../../includes/db/group_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 3 group deletions per hour)
checkRateLimit($user_guid, 'delete_group', 3, 3600);

//Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    sendError("Invalid JSON in request body", 400);
}

//Validate required fields
if (!isset($data['group_guid'])) {
    sendError("Missing required field: group_guid", 400);
}

$groupGuid = trim($data['group_guid']);

//Validate group_guid format
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group_guid format", 400);
}

//Get group and verify it exists
list($group, $error) = getGroupChatByGuid($groupGuid);
if (!$group) {
    sendError($error ?: "Group not found", 404);
}

//Verify user is an admin of the group
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    sendError("Only group admins can delete the group", 403);
}

//Get all members before deleting (for notification)
list($members) = getGroupMembersByGuid($groupGuid);

//Start transaction
$conn = getDbConnection();
mysqli_begin_transaction($conn);

try {
    //When deleting a group, directly remove all members (bypass admin check)
    $deleteMembersSql = "DELETE FROM group_members WHERE group_guid = uuid_to_bin(?, true)";
    $deleteMembersStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($deleteMembersStmt, $deleteMembersSql)) {
        mysqli_rollback($conn);
        sendError("Failed to prepare member deletion", 500);
    }
    mysqli_stmt_bind_param($deleteMembersStmt, "s", $groupGuid);
    if (!mysqli_stmt_execute($deleteMembersStmt)) {
        mysqli_stmt_close($deleteMembersStmt);
        mysqli_rollback($conn);
        sendError("Failed to remove group members", 500);
    }
    mysqli_stmt_close($deleteMembersStmt);
    
    //Mark group as inactive/deleted
    $deactivateGroupSql = "UPDATE group_chats SET is_active = 0 WHERE group_guid = uuid_to_bin(?, true)";
    $deactivateStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($deactivateStmt, $deactivateGroupSql)) {
        mysqli_rollback($conn);
        sendError("Failed to prepare group deactivation", 500);
    }
    mysqli_stmt_bind_param($deactivateStmt, "s", $groupGuid);
    if (!mysqli_stmt_execute($deactivateStmt)) {
        mysqli_stmt_close($deactivateStmt);
        mysqli_rollback($conn);
        sendError("Failed to deactivate group", 500);
    }
    mysqli_stmt_close($deactivateStmt);
    
    mysqli_commit($conn);
    
    //Broadcast group deleted notification to all former members
    if (is_array($members)) {
        foreach ($members as $member) {
            if ($member['user_guid'] != $user_guid) {
                //Notify each member individually via WebSocket
                $notificationData = [
                    'type' => 'group_deleted',
                    'action' => 'group_deleted',
                    'group_guid' => $groupGuid,
                    'group_name' => $group['group_name'],
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'status' => true,
                    'loggedIn' => true
                ];
                sendToUserByGuid($member['user_guid'], $notificationData);

                //Create persistent notification for notifications page
                list($notificationResult) = createGroupNotificationByGuid(
                    $member['user_guid'],
                    $groupGuid,
                    'group_deleted',
                    $group['group_name']
                );

                if ($notificationResult) {
                    //Send real-time notification to user's notifications page
                    if (function_exists('sendGroupNotificationCreatedByGuid')) {
                        sendGroupNotificationCreatedByGuid(
                            $member['user_guid'],
                            $notificationResult['notification_guid'],
                            $groupGuid,
                            $group['group_name'],
                            'group_deleted',
                            false 
                        );
                    }
                }
            }
        }
    }
    
    sendResponse(
        true,
        [
            'group_guid' => $groupGuid,
            'group_name' => $group['group_name']
        ],
        "Group has been permanently deleted",
        200
    );
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    app_log($e->getMessage());
    sendError("An error occurred while deleting the group", 500);
}