<?php
/* GET USER PROFILE IMAGE ENDPOINT

GET /api/account-api.php?action=get-image

Returns the profile image URL for a specific user. */

require_once __DIR__ . '/../../includes/db/profileImage.php';

$targetUserGuid = $_GET['user_guid'] ?? null; //Get user_guid parameter

if (!$targetUserGuid) {
    sendError("Missing required parameter: user_guid", 400);
}

$targetUserGuid = validateUserGuid($targetUserGuid, 'user_guid');

try {
    //Get profile image URL
    $imageUrl = getProfileImageUrlByGuid($targetUserGuid);
    
    sendResponse(true, [
        'url' => $imageUrl
    ], "Profile image URL retrieved successfully");
    
} catch (Exception $e) {
    app_log("Error in get-image endpoint: " . $e->getMessage());
    sendError("Failed to get profile image", 500);
}