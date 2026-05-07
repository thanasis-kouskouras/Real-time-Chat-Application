<?php
/* DELETE FRIEND ENDPOINT

POST /api/friend-api.php?action=delete
 
Removes an existing friend connection. */

require_once __DIR__ . '/../../includes/searchFunctions.php';
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

//Rate limit (burst protection + daily cap)
checkRateLimit($user_guid, 'delete_friend_minute', 10, 60); // max 10/minute (bot protection)
checkRateLimit($user_guid, 'delete_friend_daily',  200, 86400); // max 200/24h (daily cap)

//Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

//Validate required fields
if (!isset($input['friend_user_guid'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required field: friend_user_guid']);
    exit;
}

$friendUserGuid = validateUserGuid($input['friend_user_guid'], 'friend_user_guid');

//Check if friend user exists
list($friendUser, $error) = getUserByGuid($friendUserGuid);
if ($error !== "" || !$friendUser) {
    sendError("User not found", 404);
}

/* Mark all messages as read between both users before deleting friendship.
This prevents unread indicators from reappearing if they become friends again. */
markAllMessagesReadBetweenUsersByGuid($user_guid, $friendUserGuid);

$status = "Reject";
$notificationStatus = "Yes";
$result = deleteFriendByGuid($user_guid, $friendUserGuid, $status, $notificationStatus);

if (!$result) {
    sendError("Failed to delete friend. The friendship may not exist.", 400);
}

//Broadcast notification to the friend
$notificationData = [
    'type' => 'friend_deleted',
    'action' => 'friend_deleted',
    'from_user_guid' => $user_guid
];

sendToUserByGuid($friendUserGuid, $notificationData);

//Return success response
sendResponse(true, ['message' => 'Friend deleted successfully']);