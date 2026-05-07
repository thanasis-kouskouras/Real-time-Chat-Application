<?php
/* LIST ALL USERS ENDPOINT

GET /api/admin?action=list-users
  
Returns all users with their details. */

require_once __DIR__ . '/../../includes/upload/UploadController.php';

checkRateLimit($adminGuid, 'admin_list_users', 30, 60);

//Get all users
list($users, $error) = getAllUsers();

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($users)) {
    sendError('Failed to retrieve users', 500);
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
], 'Users retrieved successfully', 200);