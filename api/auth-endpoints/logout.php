<?php
/* LOGOUT ENDPOINT

POST /api/auth?action=logout
 
Requires authentication.
This endpoint is included from auth-api.php which already includes api-response.php and functions.inc.php. */

//Include WebSocket notifications to immediately set user offline
require_once __DIR__ . '/../../includes/websocket_notifications.php';

//Check if user is logged in
list($user) = is_user_logged_in();

if (!is_array($user)) {
    sendError('Not authenticated', 401);
}

$user_guid = $user["user_guid"];

/* Step 1: Send logout notification to WebSocket server to mark user as logging out.
This will set the loggingOutUsers flag in ChatController to skip grace period. */
$logoutNotification = [
    'action' => 'server_notification',
    'type' => 'user_logout',
    'user_guid' => $user_guid,
    'status' => true,
    'loggedIn' => true
];
sendWebSocketNotification($logoutNotification);

//Step 2: Immediately set user status to Offline in database
list($statusSuccess, $statusError) = updateUserStatusByGuid($user_guid, 'Offline');
if ($statusError !== "") {
    app_log("Warning: Failed to update user status to Offline during logout: $statusError");
    //Continue with logout even if status update fails
}

//Step 3: Get user's friends and notify them immediately
list($friends, $friendsError) = getFriendsByGuid($user_guid);
if ($friendsError === "" && is_array($friends) && count($friends) > 0) {
    //Send immediate offline status notification to all friends
    $statusData = [
        'action' => 'updateUserStatus',
        'friend_user_guid' => $user_guid,
        'userStatus' => 'Offline',
        'color' => 'salmon',
        'status' => true,
        'loggedIn' => true
    ];

    foreach ($friends as $friend) {
        $friendGuid = $friend['user_guid'] ?? null;
        if ($friendGuid && isValidGuid($friendGuid)) {
            sendToUserByGuid($friendGuid, $statusData);
        }
    }
}

//Perform logout (clears tokens, cookies, session)
if (logout($user_guid)) {
    sendResponse(true, null, 'Logout successful', 200);
} else {
    sendError('Logout failed. Please try again.', 500);
}