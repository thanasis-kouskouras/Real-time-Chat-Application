<?php
/* ADMIN LEAVE GROUP ENDPOINT
 
POST /api/group-chat-api.php?action=admin_leave
 
Handles admin leaving a group with successor selection. */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');
require_once(dirname(__FILE__) . '/../../includes/db/group_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 5 admin-leave requests per minute)
checkRateLimit($user_guid, 'admin_leave_group', 5, 60);

//Handle both JSON and form data
$input = getInput();
$groupGuid = isset($input['group_guid']) ? trim($input['group_guid']) : '';
$successorGuid = isset($input['successor_guid']) ? trim($input['successor_guid']) : '';

//Validate required fields
if (empty($groupGuid)) {
    sendError("Missing required field: group_guid", 400);
}

if (empty($successorGuid)) {
    sendError("Missing required field: successor_guid", 400);
}

//Validate GUIDs
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group GUID", 400);
}

if (!isValidGuid($successorGuid)) {
    sendError("Invalid successor GUID", 400);
}

//Reject self-selection immediately before any DB lookups
if ($successorGuid === $user_guid) {
    sendError("You cannot select yourself as successor", 400);
}

//Check if user is a member of the group
if (!isGroupMemberByGuid($groupGuid, $user_guid)) {
    sendError("You are not a member of this group", 404);
}

//Check if user is an admin
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    sendError("Only admins can use this endpoint. Use the regular leave endpoint.", 403);
}

//Check if group exists
list($group, $groupError) = getGroupChatByGuid($groupGuid);
if (!$group) {
    sendError($groupError ?: "Group not found", 404);
}

//Get user details for notification
list($user) = getUserByGuid($user_guid);

//Get all members
list($members) = getGroupMembersByGuid($groupGuid);
if (!is_array($members)) {
    sendError("Failed to retrieve group members", 500);
}

$memberCount = count($members);

//Validate successor is a member of the group
$successorMember = null;
foreach ($members as $member) {
    if ($member['user_guid'] === $successorGuid) {
        $successorMember = $member;
        break;
    }
}

if (!$successorMember) {
    sendError("Selected user is not a member of this group", 400);
}

//Check if successor is banned
list($successorUser) = getUserByGuid($successorGuid);
if (!$successorUser) {
    sendError("Successor user not found", 404);
}
if (isset($successorUser['user_banned']) && $successorUser['user_banned'] == 1) {
    sendError("Selected user is banned and cannot become admin", 400);
}

//Start transaction
$conn = getDbConnection();
mysqli_begin_transaction($conn);

try {
    /* Case 1: Group will have 2 or fewer members after leaving (deactivate the group).
    But still assign the new admin before deactivating. */
    if ($memberCount <= 3) {
        //Promote successor first (before removing admin)
        list($promoteResult, $promoteError) = updateMemberRoleByGuid($groupGuid, $successorGuid, 'admin');
        if (!$promoteResult) {
            mysqli_rollback($conn);
            sendError($promoteError ?: "Failed to promote successor", 500);
        }

        //Remove the admin
        list($removeResult, $removeError) = removeGroupMemberByGuid($groupGuid, $user_guid);
        if (!$removeResult) {
            mysqli_rollback($conn);
            sendError($removeError ?: "Failed to leave group", 500);
        }

        //Deactivate the group
        list($deactivateResult, $deactivateError) = deactivateGroupChatByGuid($groupGuid);
        if (!$deactivateResult) {
            mysqli_rollback($conn);
            sendError($deactivateError ?: "Failed to deactivate group", 500);
        }

        //Clear unread counts for all remaining members
        clearGroupUnreadCountsByGuid($groupGuid);

        mysqli_commit($conn);

        $remainingMemberCount = $memberCount - 1;

        //Create "became_admin" notification for successor
        list($notifResult) = createGroupNotificationByGuid(
            $successorGuid,
            $groupGuid,
            'became_admin',
            $group['group_name']
        );

        if ($notifResult && function_exists('sendGroupNotificationCreatedByGuid')) {
            sendGroupNotificationCreatedByGuid(
                $successorGuid,
                $notifResult['notification_guid'],
                $groupGuid,
                $group['group_name'],
                'became_admin',
                false
            );
        }

        //Broadcast role update to group
        if (function_exists('broadcastRoleUpdatedByGuid')) {
            broadcastRoleUpdatedByGuid(
                $groupGuid,
                $successorGuid,
                $successorMember['user_username'],
                'admin',
                $user_guid
            );
        }

        //Broadcast group deactivation to remaining members
        if (function_exists('broadcastGroupDeactivatedByGuid')) {
            broadcastGroupDeactivatedByGuid($groupGuid, $remainingMemberCount, $group['group_name']);
        }

        //Send deactivation notification to remaining members
        list($remainingMembers) = getGroupMembersByGuid($groupGuid);
        if (is_array($remainingMembers)) {
            foreach ($remainingMembers as $member) {
                //Skip the new admin (they already got the became_admin notification)
                if ($member['user_guid'] === $successorGuid) {
                    continue;
                }

                list($deactNotifResult) = createGroupNotificationByGuid(
                    $member['user_guid'],
                    $groupGuid,
                    'group_deactivated',
                    $group['group_name']
                );

                if ($deactNotifResult && function_exists('sendGroupNotificationCreatedByGuid')) {
                    sendGroupNotificationCreatedByGuid(
                        $member['user_guid'],
                        $deactNotifResult['notification_guid'],
                        $groupGuid,
                        $group['group_name'],
                        'group_deactivated',
                        false
                    );
                }
            }
        }

        //Broadcast member left
        if ($user && is_array($user)) {
            broadcastMemberLeftByGuid(
                $groupGuid,
                $user_guid,
                $user['user_username'],
                'left',
                $remainingMemberCount,
                $group['group_name'],
                true 
            );
        }

        sendResponse(true, [
            'group_guid' => $groupGuid,
            'group_deactivated' => true,
            'new_admin_guid' => $successorGuid,
            'new_admin_username' => $successorMember['user_username'],
            'remaining_members' => $remainingMemberCount
        ], "You have left the group. " . $successorMember['user_username'] . " is now the admin. Group has been deactivated.", 200);
    }

    //Case 2: Normal leave with successor promotion (group stays active)
    list($promoteResult, $promoteError) = updateMemberRoleByGuid($groupGuid, $successorGuid, 'admin');
    if (!$promoteResult) {
        mysqli_rollback($conn);
        sendError($promoteError ?: "Failed to promote new admin", 500);
    }

    //Remove the user from the group
    list($removeResult, $removeError) = removeGroupMemberByGuid($groupGuid, $user_guid);
    if (!$removeResult) {
        mysqli_rollback($conn);
        sendError($removeError ?: "Failed to leave group", 500);
    }

    mysqli_commit($conn);

    //Get updated member count
    list($updatedMembers) = getGroupMembersByGuid($groupGuid);
    $updatedMemberCount = is_array($updatedMembers) ? count($updatedMembers) : 0;

    //Store data for notifications
    $successorUsername = $successorMember['user_username'];
    $leavingUsername = ($user && is_array($user)) ? $user['user_username'] : '';
    $groupName = $group['group_name'];

    /* First send HTTP response, before any WebSocket operations that might timeout/fail
    Manually output JSON here instead of using sendResponse() because sendResponse() calls exit(), which would prevent WebSocket notifications from running */
    $responseData = [
        'success' => true,
        'message' => "You have left the group. " . $successorUsername . " is now the admin.",
        'group_guid' => $groupGuid,
        'group_deactivated' => false,
        'new_admin_guid' => $successorGuid,
        'new_admin_username' => $successorUsername,
        'remaining_members' => $updatedMemberCount
    ];

    http_response_code(200);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($responseData);

    //Flush output to ensure client receives response immediately
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    //If running under FastCGI, finish the request so client gets response immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    //Do notifications in background (client has already received response) and then create "became_admin" notification for successor
    try {
        list($notifResult) = createGroupNotificationByGuid(
            $successorGuid,
            $groupGuid,
            'became_admin',
            $groupName
        );

        if ($notifResult && function_exists('sendGroupNotificationCreatedByGuid')) {
            sendGroupNotificationCreatedByGuid(
                $successorGuid,
                $notifResult['notification_guid'],
                $groupGuid,
                $groupName,
                'became_admin',
                true 
            );
        }

        //Broadcast role update to group
        if (function_exists('broadcastRoleUpdatedByGuid')) {
            broadcastRoleUpdatedByGuid(
                $groupGuid,
                $successorGuid,
                $successorUsername,
                'admin',
                $user_guid
            );
        }

        //Broadcast member left
        if ($leavingUsername) {
            broadcastMemberLeftByGuid(
                $groupGuid,
                $user_guid,
                $leavingUsername,
                'left',
                $updatedMemberCount,
                $groupName,
                false
            );
        }
    } catch (Exception $notifException) {
        //Log notification errors but don't affect the response
        app_log("Admin Leave - Notification error (non-critical): " . $notifException->getMessage());
    }

    exit; //Ensure exit after Case 2

} catch (Exception $e) {
    mysqli_rollback($conn);
    app_log($e->getMessage());
    sendError("An error occurred while leaving the group", 500);
}