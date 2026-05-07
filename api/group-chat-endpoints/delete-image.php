<?php
/* DELETE GROUP IMAGE ENDPOINT
 
Handles POST/DELETE requests for deleting group image.
Requires admin role in the group. */

require_once __DIR__ . '/../../includes/websocket_notifications.php';
require_once __DIR__ . '/../../includes/dbh.inc.php';

//Verify POST or DELETE method
if ($method !== 'POST' && $method !== 'DELETE') {
    sendError('Method not allowed', 405);
}

//Rate limit (max 10 image deletions per hour)
checkRateLimit($user_guid, 'delete_group_image', 10, 3600);

//Get group GUID from POST data, JSON body, or query params
$groupGuid = '';
if ($method === 'POST') {
    $groupGuid = isset($_POST['group_guid']) ? trim($_POST['group_guid']) : '';
} else if ($method === 'DELETE') {
    //Try to get from JSON body first, then query params
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
        $groupGuid = isset($data['group_guid']) ? trim($data['group_guid']) : '';
    }
    
    //Fallback to query params if no JSON body
    if (empty($groupGuid)) {
        $groupGuid = isset($_GET['group_guid']) ? trim($_GET['group_guid']) : '';
    }
}

//Validate group GUID
if (empty($groupGuid)) {
    sendError('Group GUID is required', 400);
}

if (!isValidGuid($groupGuid)) {
    sendError('Invalid group GUID format', 400);
}

//Verify user is group admin
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    sendError('Only group admins can delete the group image', 403);
}

//Check if group has a custom image by checking the database
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT file_path FROM group_chats WHERE group_guid = uuid_to_bin(?, true)");
$stmt->bind_param("s", $groupGuid);
$stmt->execute();
$result = $stmt->get_result();
$groupData = $result->fetch_assoc();
$stmt->close();

if (!$groupData || empty($groupData['file_path'])) {
    sendError('No custom group image to delete', 400);
}

//Delete the image by clearing database fields
try {
    //Store file path before clearing for physical file deletion
    $filePath = $groupData['file_path'];

    //Clear the file path and image_guid in database
    $updateStmt = $conn->prepare("UPDATE group_chats SET group_image = NULL, file_path = NULL, image_guid = NULL WHERE group_guid = uuid_to_bin(?, true)");
    $updateStmt->bind_param("s", $groupGuid);

    if (!$updateStmt->execute()) {
        $updateStmt->close();
        sendError('Failed to delete group image', 500);
    }

    $updateStmt->close();

    //Delete the physical file if it exists
    if ($filePath && file_exists($filePath)) {
        if (@unlink($filePath)) {
            app_log("Deleted group image file: $filePath");
        } else {
            app_log("Failed to delete group image file: $filePath");
        }
    }

    //Clean up any orphaned images in the group's upload directory
    $uploadsBaseDir = $GLOBALS['baseFilePath'] ?? __DIR__ . '/../../uploads/';
    $groupImageDir = rtrim($uploadsBaseDir, '/\\') . DIRECTORY_SEPARATOR . 'groups' . DIRECTORY_SEPARATOR . 'group_' . $groupGuid . DIRECTORY_SEPARATOR;

    if (is_dir($groupImageDir)) {
        $files = glob($groupImageDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                if (@unlink($file)) {
                    app_log("Deleted orphaned group image: $file");
                } else {
                    app_log("Failed to delete orphaned group image: $file");
                }
            }
        }
    }

} catch (Exception $e) {
    app_log("Group image deletion error: " . $e->getMessage());
    sendError('Deletion system error', 500);
}

//Broadcast settings updated notification
if (function_exists('broadcastSettingsUpdatedByGuid')) {
    broadcastSettingsUpdatedByGuid($groupGuid, [
        'group_image' => null,
        'file_path' => null,
        'image_guid' => null
    ], $user_guid);
}

//Return success response
sendResponse(true, [
    'group_guid' => $groupGuid
], 'Group image deleted successfully', 200);