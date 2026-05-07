<?php
/* UPLOAD PROFILE IMAGE ENDPOINT

POST /api/account-api.php?action=upload-image

Expects multipart/form-data with file field named "file" or "profile_image". */

require_once __DIR__ . '/../../includes/db/profileImage.php';
require_once __DIR__ . '/../../includes/upload/UploadController.php';

//Rate limit (10 profile image uploads per hour per user)
checkRateLimit($user_guid, 'upload_profile_image', 10, 3600);

$file = null;
if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $file = $_FILES['file'];
} elseif (isset($_FILES['profile_image']) && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
    $file = $_FILES['profile_image'];
}

//Check if file was uploaded
if (!$file) {
    sendError('No file uploaded', 400, ['file' => 'File is required']);
}

$fileName = $file['name'];
$fileSize = $file['size'];
$fileError = $file['error'];

//Check for upload errors
if ($fileError !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension'
    ];

    $message = $errorMessages[$fileError] ?? 'Unknown upload error';
    sendError($message, 400);
}

//Get file extension
$fileExt = explode('.', $fileName);
$fileActualExt = strtolower(end($fileExt));

//Validate file type
$allowed = ['jpg', 'jpeg', 'png'];
if (!in_array($fileActualExt, $allowed)) {
    sendError('Invalid file type. Only JPG, JPEG, and PNG are allowed', 400, ['file' => 'Invalid file type']);
}

//Validate file size
if ($fileSize >= MAX_PROFILE_IMAGE_SIZE) {
    sendError('File is too large. Maximum size is ' . (MAX_PROFILE_IMAGE_SIZE / 1024 / 1024) . 'MB', 400, ['file' => 'File too large']);
}

try {
    $uploadController = new UploadController(getDbConnection());
    $result = $uploadController->uploadProfileImage($user_guid, $file);

    if ($result['success']) {
        sendResponse(true, [
            'imageUrl' => $result['data']['url'] ?? getProfileImageUrlByGuid($user_guid)
        ], 'Profile image uploaded successfully', 200);
    } else {
        app_log("Upload system failed for user {$user_guid}: " . json_encode($result));
        sendError('Upload failed: ' . ($result['error']['message'] ?? 'Unknown error'), 500);
    }

} catch (Exception $e) {
    app_log("Upload system error for user {$user_guid}: " . $e->getMessage());
    sendError('Upload system error', 500);
}