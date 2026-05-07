<?php
/* ADD FRIEND REQUEST ENDPOINT
 
POST /api/friend-api.php?action=add

Sends a friend request to another user. */

require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Only allow POST requests
if ($method !== 'POST') {
    sendError('Method not allowed. Use POST.', 405);
}

//Rate limit (burst protection + daily cap)
checkRateLimit($user_guid, 'add_friend_minute', 10, 60); //max 10/minute (bot protection)
checkRateLimit($user_guid, 'add_friend_daily',  200, 86400); //max 200/24h (daily cap)

//Get JSON input
$input = getJsonInput();

if (!$input) {
    sendError('Invalid JSON input', 400);
}

//Validate required fields
if (!isset($input['to_user_guid'])) {
    sendError('Missing required field: to_user_guid', 400);
}

//Use GUID-only parameter
$toUserGuid = validateUserGuid($input['to_user_guid'], 'to_user_guid');

//Check if trying to add self
if ($toUserGuid === $user_guid) {
    sendError("You cannot send a friend request to yourself", 400);
}

//Check if target user exists
list($toUser, $error) = getUserByGuid($toUserGuid);
if ($error !== "" || !$toUser) {
    sendError("User not found", 404);
}

//Send friend request
$status = "Pending";
$notificationStatus = "Yes";
$result = addFriendByGuid($user_guid, $toUserGuid, $status, $notificationStatus);

if (!$result) {
    //Check what the existing status is to provide better error message
    $conn = getDbConnection();
    $checkSql = "SELECT request_status FROM addrequest WHERE 
                 ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)) OR
                  (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->bind_param("ssss", $user_guid, $toUserGuid, $toUserGuid, $user_guid);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($row = $checkResult->fetch_assoc()) {
            $existingStatus = $row['request_status'];
            $checkStmt->close();
            
            if ($existingStatus === 'Pending') {
                sendError("A friend request is already pending with this user.", 400);
            } else if ($existingStatus === 'Confirm') {
                sendError("You are already friends with this user.", 400);
            }
        }
        $checkStmt->close();
    }
    
    sendError("Failed to send friend request. Please try again.", 400);
}

//Send notification to target user if they're offline
if (!isUserOnlineByGuid($toUser['user_guid'])) {
    sendFriendRequestNotificationEmail($toUserGuid, $user['user_username']);
    //Email notification sent (logged internally)
}

//Broadcast notification via WebSocket to online user
$notificationData = [
    'type' => 'friend_request',
    'action' => 'friend_request'
];

sendToUserByGuid($toUserGuid, $notificationData);

//Return success response
sendResponse(true, ['message' => 'Friend request sent successfully']);