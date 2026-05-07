<?php
/* GET FRIENDS LIST ENDPOINT
 
GET /api/friend-api.php?action=get-friends

Returns list of user's friends. */

require_once __DIR__ . '/../../includes/db/addrequest.php';
require_once __DIR__ . '/../../includes/db/profileImage.php';

//Rate limit (max 30 requests per minute)
checkRateLimit($user_guid, 'get_friends', 30, 60);

//Get user's friends
list($friends, $error) = getFriendsByGuid($user_guid);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($friends)) {
    sendError('Failed to retrieve friends', 500);
}

//Format friend data for response
$formattedFriends = array_map(function($friend) {
    $friendGuid = $friend['user_guid'];
    
    //Get profile image URL using the helper function
    $profileImageUrl = getProfileImageUrlByGuid($friendGuid);
    
    return [
        'friend_guid' => $friendGuid,
        'username' => $friend['user_username'],
        'status' => $friend['user_status'],
        'profile_image_url' => $profileImageUrl
    ];
}, $friends);

sendResponse(true, [
    'friends' => $formattedFriends
], 'Friends retrieved successfully', 200);