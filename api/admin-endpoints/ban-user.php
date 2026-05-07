<?php
/* BAN USER ENDPOINT

POST/PUT /api/admin?action=ban-user

Request body:
{
  "user_guid": "user-guid-here"
} */

checkRateLimit($adminGuid, 'admin_ban_user', 20, 60);

//Get input data
$input = getInput();

$targetUserGuid = $input['user_guid'] ?? '';

//Validate input
if (empty($targetUserGuid)) {
    sendError('Valid user GUID is required', 400, ['user_guid' => 'User GUID is required']);
}

if (!isValidGuid($targetUserGuid)) {
    sendError('Invalid user GUID format', 400);
}

//Check if admin can ban this user
if (!canBanUserByGuid($adminGuid, $targetUserGuid)) {
    if ($adminGuid === $targetUserGuid) {
        sendError('You cannot ban yourself', 400);
    }
    sendError('You do not have permission to ban this user', 403);
}

//Ban the user
list($success, $error) = banUserByGuid($targetUserGuid);

if ($error !== "") {
    sendError($error, 500);
}

if (!$success) {
    sendError('Failed to ban user', 500);
}

//Broadcast to all connected users (notifies group chats to update the banned member's status)
require_once __DIR__ . '/../../includes/websocket_notifications.php';
$broadcastData = [
    'action' => 'userBanned',
    'type' => 'user_banned_broadcast',
    'user_guid' => $targetUserGuid,
    'banned' => true,
    'status' => 'Offline',
    'message' => 'User has been banned'
];

broadcastToAll($broadcastData);

//Success
sendResponse(true, [
    'user_guid' => $targetUserGuid
], 'User banned successfully', 200);