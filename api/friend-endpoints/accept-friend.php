<?php
/* ACCEPT FRIEND REQUEST ENDPOINT

POST /api/friend-api.php?action=accept

Accepts a pending friend request. */

require_once __DIR__ . '/../../includes/searchFunctions.php';
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

//Rate limit (max 20 accepts per minute)
checkRateLimit($user_guid, 'accept_friend', 20, 60);

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

//Check if the request actually exists
$conn = getDbConnection();
$checkSql = "SELECT request_status FROM addrequest WHERE request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ss", $fromUserGuid, $user_guid);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if (!$checkResult->fetch_assoc()) {
    sendError("Friend request not found", 404);
}
$checkStmt->close();

//Accept friend request
$status = "Confirm";
$notificationStatus = "Yes";
$result = acceptRequestByGuid($fromUserGuid, $user_guid, $status, $notificationStatus);

if (!$result) {
    sendError("Failed to accept friend request. The request may not exist.", 400);
}

//Broadcast notification to the requester
$notificationData = [
    'type' => 'friend_accepted',
    'action' => 'friend_accepted'
];

sendToUserByGuid($fromUserGuid, $notificationData);

//Return success response
sendResponse(true, ['message' => 'Friend request accepted']);