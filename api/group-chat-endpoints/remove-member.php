<?php
/* REMOVE MEMBER ENDPOINT
  
Handles POST requests for removing a member from a group chat. */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');
require_once(dirname(__FILE__) . '/../../includes/db/group_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 10 remove-member requests per minute)
checkRateLimit($user_guid, 'remove_member', 10, 60);

//Get POST data (support both form data and JSON)
$groupGuid = '';
$memberToRemoveGuid = '';

//Try form data first
if (isset($_POST['group_guid']) && isset($_POST['user_guid'])) {
    $groupGuid = trim($_POST['group_guid']);
    $memberToRemoveGuid = trim($_POST['user_guid']);
} else {
    //Try JSON
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    if ($data && isset($data['group_guid']) && isset($data['user_guid'])) {
        $groupGuid = trim($data['group_guid']);
        $memberToRemoveGuid = trim($data['user_guid']);
    }
}

//Validate required fields
if (empty($groupGuid) || empty($memberToRemoveGuid) || !isValidGuid($groupGuid) || !isValidGuid($memberToRemoveGuid)) {
    sendErrorResponse("Missing or invalid required fields: group_guid, user_guid", 400);
}

//Authorization checks
$group = requireActiveGroupByGuid($groupGuid);
requireGroupAdminByGuid($groupGuid, $user_guid);

//Prevent admin from removing themselves (they should use leave endpoint)
if ($memberToRemoveGuid === $user_guid) {
    sendErrorResponse("Admins cannot remove themselves. Use the leave group endpoint instead", 400);
}

//Check if the user to remove is actually a member
if (!isGroupMemberByGuid($groupGuid, $memberToRemoveGuid)) {
    sendErrorResponse("User is not a member of this group", 404);
}

//Get user details before removing for notification
list($removedUser) = getUserByGuid($memberToRemoveGuid);

//Remove the member
list($result, $removeError) = removeGroupMemberByGuid($groupGuid, $memberToRemoveGuid);

if (!$result) {
    sendErrorResponse($removeError ?: "Failed to remove member", 500);
}

//Get updated member count
list($members) = getGroupMembersByGuid($groupGuid);
$memberCount = is_array($members) ? count($members) : 0;

//Check if group should be deactivated
$groupDeactivated = false;
if ($memberCount < 3) {
    //Deactivate the group
    list($deactivateResult) = deactivateGroupChatByGuid($groupGuid);
    if ($deactivateResult) {
        $groupDeactivated = true;

        //Clear unread counts for all remaining members to prevent permanent unread indicators
        clearGroupUnreadCountsByGuid($groupGuid);

        //Broadcast group deactivation notification to all remaining members
        if (function_exists('broadcastGroupDeactivatedByGuid')) {
            broadcastGroupDeactivatedByGuid($groupGuid, $memberCount, $group['group_name']);
        }

        /* Send direct notification to the admin who is performing the removal.
        This ensures the admin's UI updates even if broadcast doesn't reach them. */
        if (function_exists('sendToUserByGuid')) {
            $adminNotification = [
                'type' => 'group_deactivated',
                'action' => 'group_deactivated',
                'group_guid' => $groupGuid,
                'group_name' => $group['group_name'],
                'member_count' => $memberCount,
                'deactivated_at' => date('Y-m-d H:i:s'),
                'status' => true,
                'loggedIn' => true
            ];
            sendToUserByGuid($user_guid, $adminNotification);
        }

        //Create deactivation notifications for all remaining members
        if (is_array($members)) {
            foreach ($members as $member) {
                list($notifResult) = createGroupNotificationByGuid(
                    $member['user_guid'],
                    $groupGuid,
                    'group_deactivated',
                    $group['group_name']
                );
                if ($notifResult && function_exists('sendGroupNotificationCreatedByGuid')) {
                    sendGroupNotificationCreatedByGuid(
                        $member['user_guid'],
                        $notifResult['notification_guid'],
                        $groupGuid,
                        $group['group_name'],
                        'group_deactivated',
                        false
                    );
                }
            }
        }
    }
}

//Broadcast member removed notification to all group members
if ($removedUser && is_array($removedUser)) {
    //Exclude the admin who is removing the member from receiving the notification
    broadcastMemberLeftByGuid(
        $groupGuid,
        $memberToRemoveGuid,
        $removedUser['user_username'],
        'removed',
        $memberCount,
        $group['group_name'],
        $groupDeactivated,
        [$user_guid]
    );

    //Create persistent notification for the removed user
    list($notificationResult) = createGroupNotificationByGuid(
        $memberToRemoveGuid,
        $groupGuid,
        'removed_from_group',
        $group['group_name']
    );

    if ($notificationResult) {
        //Send real-time notification to user's notifications page
        if (function_exists('sendGroupNotificationCreatedByGuid')) {
            sendGroupNotificationCreatedByGuid(
                $memberToRemoveGuid,
                $notificationResult['notification_guid'],
                $groupGuid,
                $group['group_name'],
                'removed_from_group',
                false
            );
        }
        //Send direct removed_from_group notification for realtime UI updates
        if (function_exists('sendRemovedFromGroupByGuid')) {
            sendRemovedFromGroupByGuid(
                $memberToRemoveGuid,
                $groupGuid,
                $group['group_name']
            );
        }
    }
}

//Build response message
$message = 'Member removed successfully';
if ($groupDeactivated) {
    $message .= '. Group has been deactivated as it now has fewer than 3 members.';
}

//Return response
sendSuccessResponse([
    'group_guid' => $groupGuid,
    'removed_user_guid' => $memberToRemoveGuid,
    'remaining_members' => $memberCount,
    'group_deactivated' => $groupDeactivated
], $message);