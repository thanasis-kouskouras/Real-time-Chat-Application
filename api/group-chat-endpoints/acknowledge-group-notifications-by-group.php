<?php
/* ACKNOWLEDGE GROUP NOTIFICATION ENDPOINT BY GROUP ENDPOINT
 
POST /api/group-chat-api.php?action=acknowledge_group_notifications_by_group
 
Acknowledges all pending notifications for a specific group.
Used when user interacts with a group to automatically dismiss related notifications. */

require_once __DIR__ . '/../../includes/db/group_notifications.php';
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 20 requests per minute)
checkRateLimit($user_guid, 'acknowledge_group_notifications_by_group', 20, 60);

//Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    sendError("Invalid JSON in request body", 400);
}

//Validate required field
if (!isset($data['group_guid']) || empty($data['group_guid'])) {
    sendError("Missing required field: group_guid", 400);
}

$groupGuid = trim($data['group_guid']);

//Validate GUID format
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group_guid format", 400);
}

//Get optional notification types filter
$notificationTypes = [];
if (isset($data['notification_types']) && is_array($data['notification_types'])) {
    //Validate notification types
    $validTypes = ['added_to_group', 'group_deleted', 'group_deleted_account_removed', 'group_deactivated', 'group_reactivated', 'removed_from_group', 'became_admin'];
    foreach ($data['notification_types'] as $type) {
        if (in_array($type, $validTypes)) {
            $notificationTypes[] = $type;
        }
    }
}

//Acknowledge the notifications
list($acknowledgedNotifications, $error) = acknowledgeGroupNotificationsByGroupGuid($groupGuid, $user_guid, $notificationTypes);

if ($error) {
    sendError($error, 400);
}

//If notifications were acknowledged, broadcast to user for real-time UI update on other tabs
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

sendResponse(true, [], 'Notifications acknowledged', 200);