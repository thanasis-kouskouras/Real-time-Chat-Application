<?php
/* DELETE PROFILE IMAGE ENDPOINT

DELETE/POST /api/account-api.php?action=delete-image */

require_once __DIR__ . '/../../includes/db/profileImage.php';
require_once __DIR__ . '/../../includes/upload/UploadController.php';

//Rate limit (10 profile image deletions per hour per user)
checkRateLimit($user_guid, 'delete_profile_image', 10, 3600);

//Get user's current image info
$imageInfo = getProfileImageInfoByGuid($user_guid);

if (!$imageInfo) {
    sendError('No profile image to delete', 400);
}

try {
    $uploadController = new UploadController(getDbConnection());
    $result = $uploadController->deleteProfileImage($user_guid);
    
    if (!$result['success']) {
        sendError($result['error']['message'] ?? 'Failed to delete profile image', 500);
    }

} catch (Exception $e) {
    app_log("Profile image deletion error: " . $e->getMessage());
    sendError('Deletion system error', 500);
}

sendResponse(true, null, 'Profile image deleted successfully', 200);