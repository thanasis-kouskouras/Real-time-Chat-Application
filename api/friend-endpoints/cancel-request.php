<?php
/* CANCEL FRIEND REQUEST ENDPOINT
 
POST /api/friend-api.php?action=cancel

Cancels a pending friend request that was sent. */

require_once __DIR__ . '/../../includes/searchFunctions.php';
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Only allow POST requests
if ($method !== 'POST') {
    sendError('Method not allowed', 405);
}

//Rate limit (burst protection + daily cap)
checkRateLimit($user_guid, 'cancel_request_minute', 10, 60); //max 10/minute (bot protection)
checkRateLimit($user_guid, 'cancel_request_daily',  200, 86400); //max 200/24h (daily cap)

//Get JSON input
$input = getJsonInput();

if (!$input) {
    sendError('Invalid JSON input', 400);
}

//Validate required fields
if (!isset($input['to_user_guid'])) {
    sendError('Missing required field: to_user_guid', 400);
}

$toUserGuid = validateUserGuid($input['to_user_guid'], 'to_user_guid');

//Check if target user exists
list($toUser, $error) = getUserByGuid($toUserGuid);
if ($error !== "" || !$toUser) {
    sendError("User not found", 404);
}

//Cancel friend request
$status = "Reject";
$notificationStatus = "Yes";
$result = deleteFriendByGuid($user_guid, $toUserGuid, $status, $notificationStatus);

if (!$result) {
    sendError("Failed to cancel friend request. The request may not exist.", 400);
}

//Broadcast notification to the target user
$notificationData = [
    'type' => 'friend_request_cancelled',
    'action' => 'friend_request_cancelled'
];

sendToUserByGuid($toUserGuid, $notificationData);

//Return success response
sendResponse(true, ['message' => 'Friend request cancelled']);