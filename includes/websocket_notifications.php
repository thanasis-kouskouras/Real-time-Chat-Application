<?php
/* WEBSOCKET NOTIFICATION HELPER FUNCTIONS
 
Sends notifications from PHP HTTP endpoints to WebSocket server using a persistent WebSocket connection. */

require_once(dirname(__FILE__) . '/db/group_members.php');
require_once(dirname(__FILE__) . '/functions.inc.php');
require_once(dirname(__FILE__) . '/WebSocketClient.php');

//Broadcast notification to all connected WebSocket clients
function broadcastToAll($notificationData): bool
{
    try {
        app_log(">>> broadcastToAll() called");
        app_log(">>> Broadcast type: " . ($notificationData['type'] ?? 'unknown'));
        
        //Set broadcast flag
        $notificationData['broadcast'] = true;
        $notificationData['target'] = 'all';
        
        return sendWebSocketNotification($notificationData);
        
    } catch (Exception $e) {
        app_log(">>> EXCEPTION: Broadcast error: " . $e->getMessage());
        return false;
    }
}

//Send notification via persistent WebSocket connection
function sendWebSocketNotification($notificationData): bool
{
    try {
        app_log(">>> sendWebSocketNotification() called");
        app_log(">>> Notification type: " . ($notificationData['type'] ?? 'unknown'));
        app_log(">>> Notification data: " . json_encode($notificationData));
        
        //Ensure required fields are set
        if (!isset($notificationData['status'])) {
            $notificationData['status'] = true;
        }
        if (!isset($notificationData['loggedIn'])) {
            $notificationData['loggedIn'] = true;
        }
        
        //Get persistent WebSocket client instance
        app_log(">>> Getting WebSocketClient instance...");
        $client = WebSocketClient::getInstance();
        
        //Send notification
        app_log(">>> Calling client->send()...");
        $result = $client->send($notificationData);
        
        if ($result) {
            app_log(">>> SUCCESS: WebSocket notification sent: " . ($notificationData['type'] ?? 'unknown'));
        } else {
            app_log(">>> FAILED: Failed to send WebSocket notification");
        }
        
        app_log(">>> End sendWebSocketNotification()\n");
        return $result;
        
    } catch (Exception $e) {
        app_log(">>> EXCEPTION: WebSocket notification error: " . $e->getMessage());
        return false;
    }
}

//Check if user is online by GUID
function isUserOnlineByGuid($user_guid): bool
{
    try {
        //Validate GUID format directly
        if (!isValidGuid($user_guid)) {
            app_log("Invalid user GUID format: $user_guid");
            return false;
        }
        
        $conn = getDbConnection();
        
        $sql = "SELECT user_status FROM users WHERE user_guid = uuid_to_bin(?, true)";
        $stmt = mysqli_stmt_init($conn);
        
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        
        mysqli_stmt_bind_param($stmt, "s", $user_guid);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row && $row['user_status'] === 'Active';
    } catch (Exception $e) {
        app_log("Failed to check user online status: " . $e->getMessage());
        return false;
    }
}

//Send notification to specific user
function sendToUserByGuid($user_guid, $notificationData): bool
{
    app_log("===> sendToUserByGuid() called for user GUID: $user_guid");
    app_log("===> Notification type: " . ($notificationData['type'] ?? 'unknown'));
    
    if (!isValidGuid($user_guid)) {
        app_log("===> ERROR: Invalid user GUID format: {$user_guid}");
        return false;
    }
    
    app_log("===> User GUID validated: $user_guid");
    $notificationData['user_guid'] = $user_guid;
    
    app_log("===> Calling sendWebSocketNotification()...");
    $result = sendWebSocketNotification($notificationData);
    app_log("===> sendToUserByGuid() result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n");
    
    return $result;
}

//Broadcast notification to all group members
function broadcastToGroupByGuid($groupGuid, $notificationData, $excludeUserGuids = []): bool
{
    app_log("===> broadcastToGroupByGuid() called for group GUID: $groupGuid");
    app_log("===> Notification type: " . ($notificationData['type'] ?? 'unknown'));
    app_log("===> Exclude users: " . json_encode($excludeUserGuids));
    
    //Add group_guid to notification data
    $notificationData['group_guid'] = $groupGuid;
    $notificationData['broadcast_to_group'] = true;
    $notificationData['exclude_users'] = $excludeUserGuids;
    
    app_log("===> Calling sendWebSocketNotification()...");
    $result = sendWebSocketNotification($notificationData);
    app_log("===> broadcastToGroupByGuid() result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n");
    
    return $result;
}

//Broadcast member joined notification
function broadcastMemberJoinedByGuid($groupGuid, $user_guid, $username, $memberCount, $groupName = '', $excludeUserGuids = []): bool
{
    $notificationData = [
        'type' => 'member_joined',
        'action' => 'member_joined',
        'group_guid' => $groupGuid,
        'user_guid' => $user_guid,
        'username' => $username,
        'group_name' => $groupName,
        'joined_at' => date('Y-m-d H:i:s'),
        'member_count' => $memberCount,
        'status' => true,
        'loggedIn' => true
    ];

    return broadcastToGroupByGuid($groupGuid, $notificationData, $excludeUserGuids);
}


//Broadcast member left/removed notification
function broadcastMemberLeftByGuid($groupGuid, $user_guid, $username, $reason, $memberCount, $groupName = '', $groupDeactivated = false, $excludeUserGuids = []): bool
{
    $notificationData = [
        'type' => 'member_left',
        'action' => 'member_left',
        'group_guid' => $groupGuid,
        'user_guid' => $user_guid,
        'username' => $username,
        'group_name' => $groupName,
        'reason' => $reason,
        'left_at' => date('Y-m-d H:i:s'),
        'member_count' => $memberCount,
        'group_deactivated' => $groupDeactivated,
        'status' => true,
        'loggedIn' => true
    ];

    return broadcastToGroupByGuid($groupGuid, $notificationData, $excludeUserGuids);
}


//Broadcast group settings updated notification
function broadcastSettingsUpdatedByGuid($groupGuid, $changes, $updatedByGuid): bool
{
    $notificationData = [
        'type' => 'group_settings_updated',
        'action' => 'group_settings_updated',
        'changes' => $changes,
        'updated_by' => $updatedByGuid,
        'updated_at' => date('Y-m-d H:i:s'),
        'status' => true,
        'loggedIn' => true
    ];
    
    return broadcastToGroupByGuid($groupGuid, $notificationData);
}



//Broadcast role updated notification
function broadcastRoleUpdatedByGuid($groupGuid, $user_guid, $username, $newRole, $updatedByGuid): bool
{
    $notificationData = [
        'type' => 'role_updated',
        'action' => 'role_updated',
        'user_guid' => $user_guid,
        'username' => $username,
        'new_role' => $newRole,
        'updated_at' => date('Y-m-d H:i:s'),
        'keep_user_guid' => true,
        'status' => true,
        'loggedIn' => true
    ];

    return broadcastToGroupByGuid($groupGuid, $notificationData);
}

//Broadcast group reactivated notification
function broadcastGroupReactivatedByGuid($groupGuid, $memberCount, $groupName = ''): bool
{
    $notificationData = [
        'type' => 'group_reactivated',
        'action' => 'group_reactivated',
        'group_guid' => $groupGuid,
        'group_name' => $groupName,
        'member_count' => $memberCount,
        'reactivated_at' => date('Y-m-d H:i:s'),
        'status' => true,
        'loggedIn' => true
    ];

    return broadcastToGroupByGuid($groupGuid, $notificationData);
}

//Broadcast group deactivated notification
function broadcastGroupDeactivatedByGuid($groupGuid, $memberCount, $groupName = ''): bool
{
    $notificationData = [
        'type' => 'group_deactivated',
        'action' => 'group_deactivated',
        'group_guid' => $groupGuid,
        'group_name' => $groupName,
        'member_count' => $memberCount,
        'deactivated_at' => date('Y-m-d H:i:s'),
        'status' => true,
        'loggedIn' => true
    ];

    return broadcastToGroupByGuid($groupGuid, $notificationData);
}

/* Send group notification created event to a specific user.
Used to update the notifications page in real-time. */
function sendGroupNotificationCreatedByGuid($userGuid, $notificationGuid, $groupGuid, $groupName, $type, $canChat = false): bool
{
    $notificationData = [
        'type' => 'group_notification_created',
        'notification_guid' => $notificationGuid,
        'group_guid' => $groupGuid,
        'group_name' => $groupName,
        'notification_type' => $type,
        'can_chat' => $canChat,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => true,
        'loggedIn' => true
    ];

    return sendToUserByGuid($userGuid, $notificationData);
}

/* Send direct removed_from_group notification to a user.
Used for realtime UI updates when user is removed from a group. */
function sendRemovedFromGroupByGuid($userGuid, $groupGuid, $groupName): bool
{
    $notificationData = [
        'type' => 'removed_from_group',
        'action' => 'removed_from_group',
        'group_guid' => $groupGuid,
        'group_name' => $groupName,
        'removed_at' => date('Y-m-d H:i:s'),
        'status' => true,
        'loggedIn' => true
    ];

    return sendToUserByGuid($userGuid, $notificationData);
}