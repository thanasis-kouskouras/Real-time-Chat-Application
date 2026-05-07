<?php
/* REJECT FRIEND REQUEST ENDPOINT

POST /api/friend-api.php?action=reject
 
Rejects a pending friend request. */

require_once __DIR__ . '/../../includes/searchFunctions.php';
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

//Rate limit (max 20 rejects per minute)
checkRateLimit($user_guid, 'reject_friend', 20, 60);

//Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

//Validate required fields
if (!isset($input['from_user_guid'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required field: from_user_guid']);
    exit;
}

$fromUserGuid = validateUserGuid($input['from_user_guid'], 'from_user_guid');

//Check if requester user exists
list($fromUser, $error) = getUserByGuid($fromUserGuid);
if ($error !== "" || !$fromUser) {
    sendError("User not found", 404);
}

//Reject friend request
$result = rejectRequestByGuid($fromUserGuid, $user_guid, "Reject");

if (!$result) {
    sendError("Failed to reject friend request. The request may not exist.", 400);
}

//Broadcast notification to the requester
$notificationData = [
    'type' => 'friend_rejected',
    'action' => 'friend_rejected'
];

sendToUserByGuid($fromUserGuid, $notificationData);

//Return success response
sendResponse(true, ['message' => 'Friend request rejected']);