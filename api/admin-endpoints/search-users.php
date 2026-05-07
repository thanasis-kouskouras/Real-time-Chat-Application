<?php
/* SEARCH USERS ENDPOINT
 
GET /api/admin?action=search-users&q=searchterm

Search users by username or email. */

require_once __DIR__ . '/../../includes/upload/UploadController.php';

checkRateLimit($adminGuid, 'admin_search_users', 30, 60);

//Get search query
$searchTerm = $_GET['q'] ?? '';

if (empty($searchTerm)) {
    sendError('Search term is required', 400, ['q' => 'Search query parameter is required']);
}

if (strlen($searchTerm) > 254) {
    sendError('Search term is too long', 400, ['q' => 'Maximum 254 characters allowed']);
}

//Search users
list($users, $error) = searchUsers($searchTerm);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($users)) {
    sendError('Failed to search users', 500);
}

//Initialize upload controller for getting profile image URLs
$uploadController = new UploadController(getDbConnection());

//Format user data for response
$formattedUsers = array_map(function($user) use ($uploadController) {
    return [
        'guid' => $user['user_guid'],
        'username' => $user['user_username'],
        'email' => $user['user_email'],
        'status' => $user['user_status'],
        'banned' => (bool)$user['user_banned'],
        'createdDate' => $user['user_created_date'],
        'profile_image_url' => $uploadController->getProfileImageUrl($user['user_guid'])
    ];
}, $users);

//Success
sendResponse(true, [
    'users' => $formattedUsers
], 'Search completed successfully', 200);