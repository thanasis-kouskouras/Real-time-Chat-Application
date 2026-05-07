<?php
/* LEAVE GROUP ENDPOINT
  
Handles POST requests for a user to leave a group chat.
Handles special cases (last admin promotion, last member deactivation). */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');
require_once(dirname(__FILE__) . '/../../includes/db/group_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 5 leave-group requests per minute)
checkRateLimit($user_guid, 'leave_group', 5, 60);

//Handle both JSON and form data
$input = getInput();
$groupGuid = isset($input['group_guid']) ? trim($input['group_guid']) : '';

//Validate required fields
if (empty($groupGuid)) {
    sendError("Missing required field: group_guid", 400);
}

//Validate group GUID
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group GUID", 400);
}

//Check if user is a member of the group
if (!isGroupMemberByGuid($groupGuid, $user_guid)) {
    sendError("You are not a member of this group", 404);
}

//Check if group exists
list($group, $groupError) = getGroupChatByGuid($groupGuid);
if (!$group) {
    sendError($groupError ?: "Group not found", 404);
}

//Get user details for notification
list($user) = getUserByGuid($user_guid);

//Get all members before leaving
list($members) = getGroupMembersByGuid($groupGuid);
if (!is_array($members)) {
    sendError("Failed to retrieve group members", 500);
}

$memberCount = count($members);
$isUserAdmin = isGroupAdminByGuid($groupGuid, $user_guid);

//Start transaction for complex leave logic
$conn = getDbConnection();
mysqli_begin_transaction($conn);

try {
    //Auto-acknowledge all pending notifications for the leaving user from this group
    list($acknowledgedNotifications) = acknowledgeGroupNotificationsByGroupGuid($groupGuid, $user_guid);

    //Broadcast acknowledgment events for real-time UI updates
    if (!empty($acknowledgedNotifications) && function_exists('sendToUserByGuid')) {
        foreach ($acknowledgedNotifications as $notification) {
            sendToUserByGuid($user_guid, [
                'type' => 'group_notification_acknowledged',
                'notification_guid' => $notification['notification_guid'],
                'user_guid' => $user_guid
            ]);
        }
    }

    //Case 1: Group will have 2 or fewer members after leaving (deactivate the group)
    if ($memberCount <= 3) {
        //Remove the user first
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

        //Clear unread counts for all remaining members to prevent permanent unread indicators
        clearGroupUnreadCountsByGuid($groupGuid);

        mysqli_commit($conn);
        
        //Get remaining members to notify them
        $remainingMemberCount = $memberCount - 1;

        //Broadcast group deactivation notification to all remaining members
        if (function_exists('broadcastGroupDeactivatedByGuid')) {
            broadcastGroupDeactivatedByGuid($groupGuid, $remainingMemberCount, $group['group_name']);
        }

        //Send direct notification to each remaining member to ensure they receive it
        list($remainingMembersForNotify) = getGroupMembersByGuid($groupGuid);
        if (is_array($remainingMembersForNotify) && function_exists('sendToUserByGuid')) {
            foreach ($remainingMembersForNotify as $member) {
                $deactivationNotification = [
                    'type' => 'group_deactivated',
                    'action' => 'group_deactivated',
                    'group_guid' => $groupGuid,
                    'group_name' => $group['group_name'],
                    'member_count' => $remainingMemberCount,
                    'deactivated_at' => date('Y-m-d H:i:s'),
                    'status' => true,
                    'loggedIn' => true
                ];
                sendToUserByGuid($member['user_guid'], $deactivationNotification);
            }
        }

        //Broadcast member left notification with group deactivation flag
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

        //Create deactivation notifications for all remaining members
        if (is_array($remainingMembersForNotify)) {
            foreach ($remainingMembersForNotify as $member) {
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

        $deactivationMessage = $memberCount === 1 
            ? "You have left the group. Group has been deactivated as you were the last member."
            : "You have left the group. Group has been deactivated as it now has fewer than 3 members.";
        
        sendResponse(
            true,
            [
                'group_guid' => $groupGuid,
                'group_deactivated' => true,
                'remaining_members' => $remainingMemberCount
            ],
            $deactivationMessage,
            200
        );
    }
    
    //Case 2: Last admin leaving (promote longest-standing member)
    if ($isUserAdmin) {
        //Count admins
        $adminCount = 0;
        foreach ($members as $member) {
            if ($member['role'] === 'admin') {
                $adminCount++;
            }
        }
        
        //If this is the last admin, promote the longest-standing non-admin member
        if ($adminCount === 1) {
            //Find longest-standing member (earliest joined_at) who is not the current user
            $longestStandingMember = null;
            $earliestJoinTime = null;
            
            foreach ($members as $member) {
                if ($member['user_guid'] != $user_guid) {
                    $joinTime = strtotime($member['joined_at']);
                    if ($earliestJoinTime === null || $joinTime < $earliestJoinTime) {
                        $earliestJoinTime = $joinTime;
                        $longestStandingMember = $member;
                    }
                }
            }
            
            if ($longestStandingMember) {
                //Promote the longest-standing member to admin
                list($promoteResult, $promoteError) = updateMemberRoleByGuid(
                    $groupGuid, 
                    $longestStandingMember['user_guid'], 
                    'admin'
                );
                
                if (!$promoteResult) {
                    mysqli_rollback($conn);
                    sendError($promoteError ?: "Failed to promote new admin", 500);
                }
            }
        }
    }
    
    //Remove the user from the group
    list($removeResult, $removeError) = removeGroupMemberByGuid($groupGuid, $user_guid);
    if (!$removeResult) {
        mysqli_rollback($conn);
        sendError($removeError ?: "Failed to leave group", 500);
    }
    
    mysqli_commit($conn);
    
    //Get updated member count after leaving
    list($updatedMembers) = getGroupMembersByGuid($groupGuid);
    $updatedMemberCount = is_array($updatedMembers) ? count($updatedMembers) : 0;
    
    //Broadcast member left notification
    if ($user && is_array($user)) {
        broadcastMemberLeftByGuid(
            $groupGuid,
            $user_guid,
            $user['user_username'],
            'left',
            $updatedMemberCount,
            $group['group_name'],
            false
        );
    }
    
    //Prepare response message
    $message = "You have left the group successfully";
    $responseData = [
        'group_guid' => $groupGuid
    ];
    
    if ($isUserAdmin && $adminCount === 1 && isset($longestStandingMember)) {
        $message .= ". " . $longestStandingMember['user_username'] . " has been promoted to admin.";
        $responseData['new_admin_guid'] = $longestStandingMember['user_guid'];
        $responseData['new_admin_username'] = $longestStandingMember['user_username'];
    }
    
    sendResponse(
        true,
        $responseData,
        $message,
        200
    );
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    app_log($e->getMessage());
    sendError("An error occurred while leaving the group", 500);
}