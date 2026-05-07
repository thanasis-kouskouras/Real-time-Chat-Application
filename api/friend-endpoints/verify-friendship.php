<?php
/* VERIFY FRIENDSHIP ENDPOINT

GET /api/friend-api.php?action=verify&friend_guid=X

Verifies if a friendship exists between the current user and the specified user. */

require_once __DIR__ . '/../../includes/db/addrequest.php';
require_once __DIR__ . '/../../includes/db/users.php';
require_once __DIR__ . '/../../includes/guid-utilities.php';

//Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (max 30 requests per minute)
checkRateLimit($user_guid, 'verify_friendship', 30, 60);

//Get friend_guid parameter
$friendGuid = $_GET['friend_guid'] ?? null;

if (!$friendGuid || !isValidGuid($friendGuid)) {
    sendError("Valid friend_guid parameter is required", 400);
}

// Get friend user data
list($friendData, $error) = getUserByGuid($friendGuid);
if ($error !== "" || !is_array($friendData)) {
    sendError("User not found", 404);
}

// Check friendship status
list($friendship, $error) = getConfirmedFriendByGuid($user_guid, $friendGuid);
$isFriend = ($error === "" && is_array($friendship) && count($friendship) > 0);

sendResponse(true, [
    'is_friend' => $isFriend,
    'friend_guid' => $friendGuid,
    'friend_username' => $friendData['user_username'],
    'friend_status' => $friendData['user_status']
], 'Friendship verification completed', 200);