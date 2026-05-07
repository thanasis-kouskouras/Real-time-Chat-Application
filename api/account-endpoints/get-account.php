<?php
/* GET PROFILE DATA ENDPOINT

Retrieves user profile information including username, email, and image data. */

require_once __DIR__ . '/../../includes/db/users.php';
require_once __DIR__ . '/../../includes/db/profileImage.php';

//Get user profile data
list($userData, $error) = getUserByGuid($user_guid);

if ($error !== "" || !$userData) {
    sendError('Failed to retrieve profile data', 500);
}

//Get profile image URL (returns custom image or default)
$profileImageUrl = getProfileImageUrlByGuid($user_guid);

$profileData = [
    'user_username' => $userData['user_username'],
    'user_email' => $userData['user_email'],
    'profile_image_url' => $profileImageUrl
];

sendSuccessResponse(['profile' => $profileData], 'Profile data retrieved successfully');