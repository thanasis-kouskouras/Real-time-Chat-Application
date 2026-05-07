<?php
/* UPDATE GROUP IMAGE ENDPOINT
 
Handles POST requests for updating group image. */

require_once __DIR__ . '/../../includes/websocket_notifications.php';
require_once __DIR__ . '/../../includes/upload/UploadController.php';
require_once __DIR__ . '/../../includes/dbh.inc.php';

//Verify POST method
if ($method !== 'POST') {
    sendError('Method not allowed', 405);
}

//Rate limit (max 10 image uploads per hour)
checkRateLimit($user_guid, 'update_group_image', 10, 3600);

//Get POST data
$groupGuid = isset($_POST['group_guid']) ? trim($_POST['group_guid']) : '';

//Validate group GUID
if (empty($groupGuid)) {
    sendError('Group GUID is required', 400);
}

if (!isValidGuid($groupGuid)) {
    sendError('Invalid group GUID format', 400);
}

//Check if file was uploaded
if (!isset($_FILES['group_image']) || $_FILES['group_image']['error'] !== UPLOAD_ERR_OK) {
    sendError('No image file uploaded', 400);
}

//Verify user is group admin
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    sendError('Only group admins can update the group image', 403);
}

//Handle file upload
$file = $_FILES['group_image'];
$fileName = $file['name'];
$fileSize = $file['size'];

//Get file extension
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

//Allowed extensions
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

//Validate file extension
if (!in_array($fileExt, $allowedExtensions)) {
    sendError('Invalid image type. Only JPG, JPEG, PNG, and GIF are allowed', 400);
}

//Validate file size (max 5MB)
if ($fileSize > 5 * 1024 * 1024) {
    sendError('Image is too large. Maximum size is 5MB', 400);
}

//Use simplified upload system
try {
    $uploadController = new UploadController(getDbConnection());
    $result = $uploadController->uploadGroupImage($groupGuid, $_FILES['group_image']);
    
    if (!$result['success']) {
        sendError('Failed to upload image: ' . ($result['error']['message'] ?? 'Unknown error'), 500);
    }
    
    //Get the new filename from the result
    $newFileName = basename($result['data']['file_path']);
    $uploadPath = $result['data']['file_path'];
    
} catch (Exception $e) {
    app_log("Group image upload error: " . $e->getMessage());
    sendError('Upload system error', 500);
}

//Calculate file size savings (if image was processed/optimized)
$originalSize = $fileSize;
$newSize = $result['data']['file_size'] ?? filesize($uploadPath);
$savings = $originalSize - $newSize;
$savingsPercent = $originalSize > 0 ? round(($savings / $originalSize) * 100, 1) : 0;

app_log("Group image uploaded for group {$groupGuid}: {$originalSize} bytes -> {$newSize} bytes (saved {$savingsPercent}%)");

//Broadcast settings updated notification
if (function_exists('broadcastSettingsUpdatedByGuid')) {
    broadcastSettingsUpdatedByGuid($groupGuid, ['group_image' => $newFileName], $user_guid);
}

//Return success response
sendResponse(true, [
    'group_guid' => $groupGuid,
    'group_image' => $newFileName,
    'image_url' => $result['data']['url'],
    'file_size' => $newSize,
    'savings_percent' => $savingsPercent
], 'Group image updated successfully', 200);