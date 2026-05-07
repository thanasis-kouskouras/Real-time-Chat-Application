<?php
/* SEARCH USERS ENDPOINT

GET /api/search-api.php?action=search-users&q=searchterm
  
Search users by username (excluding current user). */

require_once __DIR__ . '/../../includes/db/users.php';
require_once __DIR__ . '/../../includes/db/addrequest.php';
require_once __DIR__ . '/../../includes/db/profileImage.php';
require_once __DIR__ . '/../../includes/validation.php';

//Rate limit (max 30 searches per minute per user)
checkRateLimit($user_guid, 'search_users', 30, 60);

//Get search query
$searchTerm = $_GET['q'] ?? '';

if (empty($searchTerm)) {
    sendError('Search term is required', 400, ['q' => 'Search query parameter is required']);
}

//Validate length (max 30 matches username max length)
if (usernameTooLong($searchTerm, 30)) {
    sendError('Search term is too long', 400, ['q' => 'Maximum 30 characters allowed']);
}

//Validate format (only letters, digits, underscore, hyphen)
if (invalidUsername($searchTerm)) {
    sendError('Invalid search term', 400);
}

//Search users (excluding current user)
list($users, $error) = searchUserExceptMeByGuid($searchTerm, $user_guid);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($users)) {
    sendError('Failed to search users', 500);
}

//Format user data for response
$formattedUsers = array_map(function($user) use ($user_guid) {
    $searchUserGuid = $user['user_guid'];
    
    //Get friend request status 
    $friendshipStatus = 'none'; //none, pending_sent, pending_received, friends
    
    $friendRequest = getNotificationFromToByGuid($user_guid, $searchUserGuid);
    $reverseFriendRequest = getNotificationFromToByGuid($searchUserGuid, $user_guid);
    
    if ($friendRequest && $friendRequest['request_status'] === 'Confirm') {
        $friendshipStatus = 'friends';
    } elseif ($friendRequest && $friendRequest['request_status'] === 'Pending') {
        $friendshipStatus = 'pending_sent';
    } elseif ($reverseFriendRequest && $reverseFriendRequest['request_status'] === 'Confirm') {
        $friendshipStatus = 'friends';
    } elseif ($reverseFriendRequest && $reverseFriendRequest['request_status'] === 'Pending') {
        $friendshipStatus = 'pending_received';
    }
    
    //Get profile image
    $profileImgGuid = null;
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT bin_to_uuid(image_guid, true) as image_guid 
                           FROM profileImage 
                           WHERE user_guid = uuid_to_bin(?, true) 
                           LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $searchUserGuid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $profileImgGuid = $row['image_guid'];
        }
        $stmt->close();
    }
    
    return [
        'guid' => $user['user_guid'],
        'username' => $user['user_username'],
        'profile_image_guid' => $profileImgGuid,
        'friendshipStatus' => $friendshipStatus
    ];
}, $users);

//Success
sendResponse(true, [
    'users' => $formattedUsers,
    'total_count' => count($formattedUsers),
    'search_term' => $searchTerm
], 'Search completed successfully', 200);