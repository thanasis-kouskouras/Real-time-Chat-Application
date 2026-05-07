<?php
/* ACKNOWLEDGE GROUP NOTIFICATION ENDPOINT
 
POST /api/group-chat-api.php?action=acknowledge_group_notification
 
Marks a group notification as acknowledged (dismissed). */

require_once __DIR__ . '/../../includes/db/group_notifications.php';
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 20 requests per minute)
checkRateLimit($user_guid, 'acknowledge_group_notification', 20, 60);

//Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    sendError("Invalid JSON in request body", 400);
}

//Validate required field
if (!isset($data['notification_guid']) || empty($data['notification_guid'])) {
    sendError("Missing required field: notification_guid", 400);
}

$notificationGuid = trim($data['notification_guid']);

//Validate GUID format
if (!isValidGuid($notificationGuid)) {
    sendError("Invalid notification_guid format", 400);
}

//Get the notification before acknowledging (for WebSocket broadcast)
list($notification) = getGroupNotificationByGuid($notificationGuid);

//Acknowledge the notification
list($result, $error) = acknowledgeGroupNotificationByGuid($notificationGuid, $user_guid);

if (!$result) {
    sendError($error ?: "Failed to acknowledge notification", 400);
}

//Broadcast to user that notification was acknowledged (for real-time UI update on other tabs)
if ($notification) {
    $wsNotification = [
        'type' => 'group_notification_acknowledged',
        'notification_guid' => $notificationGuid,
        'user_guid' => $user_guid
    ];
    sendToUserByGuid($user_guid, $wsNotification);
}

sendResponse(true, [], 'Notification acknowledged', 200);