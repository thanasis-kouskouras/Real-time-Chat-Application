<?php
/* ADD MEMBERS ENDPOINT

Handles POST requests for adding members directly to a group. */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');
require_once(dirname(__FILE__) . '/../../includes/db/group_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 5 add-members requests per minute)
checkRateLimit($user_guid, 'add_members', 5, 60);

//Get input data (handle both JSON and form data)
$input = getInput();
$groupGuid = isset($input['group_guid']) ? trim($input['group_guid']) : '';

//Handle member_guids from various sources (form data arrays, JSON arrays, JSON strings)
$memberGuids = [];
if (isset($input['member_guids'])) {
    if (is_array($input['member_guids'])) {
        $memberGuids = $input['member_guids'];
    } elseif (is_string($input['member_guids'])) {
        //Handle JSON string from form data
        $decoded = json_decode($input['member_guids'], true);
        if (is_array($decoded)) {
            $memberGuids = $decoded;
        }
    }
} elseif (isset($input['new_member_guids']) && is_array($input['new_member_guids'])) {
    $memberGuids = $input['new_member_guids'];
}

//Validate inputs
if (empty($groupGuid) || !isValidGuid($groupGuid)) {
    sendError('Invalid group GUID', 400);
}

if (empty($memberGuids)) {
    sendError('No members selected', 400);
}

//Get group and verify it exists
list($group) = getGroupChatByGuid($groupGuid);
if (!$group) {
    sendError('Group not found', 404);
}

//Verify user is an admin of the group
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    sendError('Only group admins can add members', 403);
}

//Get current members
list($currentMembers) = getGroupMembersByGuid($groupGuid);
$currentMemberGuids = [];
foreach ($currentMembers as $member) {
    $currentMemberGuids[] = $member['user_guid'];
}

//Add each selected member
$addedMembers = [];
$addedNames = [];

foreach ($memberGuids as $memberGuid) {
    $memberGuid = trim($memberGuid);
    
    //Validate GUID format
    if (!isValidGuid($memberGuid)) {
        continue;
    }
    
    //Skip if already a member
    if (in_array($memberGuid, $currentMemberGuids)) {
        continue;
    }
    
    //Check if they are friends
    list($friendship1) = getConfirmedFriendByGuid($user_guid, $memberGuid);
    list($friendship2) = getConfirmedFriendByGuid($memberGuid, $user_guid);
    
    //Check if friendship exists in either direction
    $areFriends = (is_array($friendship1) && count($friendship1) > 0) || 
                  (is_array($friendship2) && count($friendship2) > 0);
    
    if (!$areFriends) {
        continue; //Skip non-friends
    }
    
    //Add member directly (no invitation)
    list($result) = addGroupMemberByGuid($groupGuid, $memberGuid, 'member');
    
    if ($result) {
        $addedMembers[] = $memberGuid;
        
        //Get username for system message
        list($user) = getUserByGuid($memberGuid);
        if ($user) {
            $addedNames[] = $user['user_username'];
        }
    }
}

//If members were added, broadcast notification
if (count($addedMembers) > 0) {
    //Build system message
    list($currentUser) = getUserByGuid($user_guid);
    $creatorName = $currentUser ? $currentUser['user_username'] : 'Someone';

    if (count($addedNames) > 1) {
        $last = array_pop($addedNames);
        $names = implode(", ", $addedNames) . " and " . $last;
    } else {
        $names = $addedNames[0] ?? "";
    }

    //Calculate new member count
    $newMemberCount = count($currentMembers) + count($addedMembers);

    //Check if group was deactivated and should now be reactivated (group needs 3+ members to be active)
    $wasDeactivated = isset($group['is_active']) && $group['is_active'] == 0;
    $groupReactivated = false;

    if ($wasDeactivated && $newMemberCount >= 3) {
        //Reactivate the group
        list($reactivateResult) = reactivateGroupChatByGuid($groupGuid);
        if ($reactivateResult) {
            $groupReactivated = true;

            //Auto-acknowledge the admin's group_deactivated notification since they reactivated the group
            list($acknowledgedNotifications) = acknowledgeGroupNotificationsByGroupGuid(
                $groupGuid,
                $user_guid,
                ['group_deactivated']
            );

            //Send WebSocket notifications to dismiss from Notifications page in real-time
            if (!empty($acknowledgedNotifications)) {
                foreach ($acknowledgedNotifications as $notification) {
                    $wsNotification = [
                        'type' => 'group_notification_acknowledged',
                        'notification_guid' => $notification['notification_guid'],
                        'user_guid' => $user_guid
                    ];
                    sendToUserByGuid($user_guid, $wsNotification);
                }
            }

            //Broadcast group reactivation notification to all members
            if (function_exists('broadcastGroupReactivatedByGuid')) {
                broadcastGroupReactivatedByGuid($groupGuid, $newMemberCount, $group['group_name'] ?? '');
            }

            //Create reactivation notifications for all existing members (not the newly added ones)
            foreach ($currentMembers as $member) {
                //Skip the admin who is adding members
                if ($member['user_guid'] === $user_guid) {
                    continue;
                }
                list($notifResult) = createGroupNotificationByGuid(
                    $member['user_guid'],
                    $groupGuid,
                    'group_reactivated',
                    $group['group_name'] ?? ''
                );
                if ($notifResult && function_exists('sendGroupNotificationCreatedByGuid')) {
                    sendGroupNotificationCreatedByGuid(
                        $member['user_guid'],
                        $notifResult['notification_guid'],
                        $groupGuid,
                        $group['group_name'] ?? '',
                        'group_reactivated',
                        true
                    );
                }
            }
        }
    }

    //Broadcast via WebSocket
    if (function_exists('broadcastMemberJoinedByGuid')) {
        foreach ($addedMembers as $memberGuid) {
            list($user) = getUserByGuid($memberGuid);
            if ($user) {
                //Exclude the admin who is adding members from receiving the notification
                broadcastMemberJoinedByGuid($groupGuid, $memberGuid, $user['user_username'], $newMemberCount, $group['group_name'] ?? '', [$user_guid]);
            }
        }
    }

    //Create group notifications for added members
    foreach ($addedMembers as $memberGuid) {
        list($notificationResult) = createGroupNotificationByGuid(
            $memberGuid,
            $groupGuid,
            'added_to_group',
            $group['group_name'] ?? ''
        );

        if ($notificationResult) {
            //Send real-time notification to user's notifications page
            sendGroupNotificationCreatedByGuid(
                $memberGuid,
                $notificationResult['notification_guid'],
                $groupGuid,
                $group['group_name'] ?? '',
                'added_to_group',
                true
            );
        }
    }
}

//Return success response
$addedCount = count($addedMembers);
$message = $addedCount === 1 ? '1 member added successfully' : "$addedCount members added successfully";

//Add reactivation message if applicable
if (isset($groupReactivated) && $groupReactivated) {
    $message .= '. Group has been reactivated as it now has 3 or more members.';
}

$responseData = [
    'group_guid' => $groupGuid,
    'added_count' => $addedCount,
    'added_members' => $addedMembers
];

//Include reactivation status in response
if (isset($groupReactivated)) {
    $responseData['group_reactivated'] = $groupReactivated;
}

sendResponse(true, $responseData, $message);