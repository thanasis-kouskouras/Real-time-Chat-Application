<?php
/* GET GROUP NOTIFICATIONS ENDPOINT
 
GET /api/group-chat-api.php?action=get_group_notifications
 
Returns pending group chat notifications for the authenticated user. */

require_once __DIR__ . '/../../includes/db/group_notifications.php';

//Only allow GET requests
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (max 30 requests per minute)
checkRateLimit($user_guid, 'get_group_notifications', 30, 60);

//Get pending notifications
list($notifications, $error) = getPendingGroupNotificationsByGuid($user_guid);

if ($notifications === false) {
    sendError($error ?: 'Failed to retrieve notifications', 500);
}

//Format notifications for response
$formattedNotifications = [];

foreach ($notifications as $notification) {
    //Determine if user can chat
    $canChat = false;
    if ($notification['notification_type'] === 'added_to_group' || $notification['notification_type'] === 'became_admin') {
        $canChat = canUserAccessGroupChatByGuid($notification['group_guid'], $user_guid);
    }

    $formattedNotifications[] = [
        'notificationGuid' => $notification['notification_guid'],
        'groupGuid' => $notification['group_guid'],
        'groupName' => $notification['group_name'],
        'type' => $notification['notification_type'],
        'datetime' => $notification['created_at'],
        'canChat' => $canChat
    ];
}

sendResponse(true, [
    'notifications' => $formattedNotifications
], 'Group notifications retrieved successfully', 200);