<?php
/* UNBAN USER ENDPOINT

POST/PUT /api/admin?action=unban-user

Request body:
{
  "user_guid": "user-guid-here"
} */

checkRateLimit($adminGuid, 'admin_unban_user', 20, 60);

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

//Unban the user
list($success, $error) = unbanUserByGuid($targetUserGuid);

if ($error !== "") {
    sendError($error, 500);
}

if (!$success) {
    sendError('Failed to unban user', 500);
}

//Broadcast to all connected users (notifies group chats to update the unbanned member's status)
require_once __DIR__ . '/../../includes/websocket_notifications.php';
$broadcastData = [
    'action' => 'userUnbanned',
    'type' => 'user_unbanned_broadcast',
    'user_guid' => $targetUserGuid,
    'banned' => false,
    'status' => 'Offline',
    'message' => 'User has been unbanned'
];

broadcastToAll($broadcastData);

//Success
sendResponse(true, [
    'user_guid' => $targetUserGuid
], 'User unbanned successfully', 200);