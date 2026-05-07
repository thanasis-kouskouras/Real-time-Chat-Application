<?php
/* UPDATE GROUP SETTINGS ENDPOINT

Handles POST requests for updating group chat settings.
Requires admin role in the group. */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 10 settings updates per hour)
checkRateLimit($user_guid, 'update_group_settings', 10, 3600);

//Get request body
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

if (!$data) {
    sendError("Invalid JSON in request body", 400);
}

//Validate required fields
if (!isset($data['group_guid'])) {
    sendError("Missing required field: group_guid", 400);
}

$groupGuid = trim($data['group_guid']);

//Validate group GUID
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group GUID", 400);
}

//Authorization checks
requireActiveGroupByGuid($groupGuid);
requireGroupAdminByGuid($groupGuid, $user_guid);

//Prepare updates array
$updates = [];

//Validate and add group_name if provided
if (isset($data['group_name'])) {
    $updates['group_name'] = validateGroupName($data['group_name']);
}

//Check if there are any updates to apply
if (empty($updates)) {
    sendError("No valid settings to update", 400);
}

//Update group settings
list($result, $updateError) = updateGroupChatByGuid($groupGuid, $updates);

if (!$result) {
    sendError($updateError ?: "Failed to update group settings", 500);
}

//Get updated group data
list($updatedGroup, $getError) = getGroupChatByGuid($groupGuid);
if (!$updatedGroup) {
    sendError($getError ?: "Failed to retrieve updated group data", 500);
}

//Broadcast settings updated notification to all group members
if (function_exists('broadcastSettingsUpdatedByGuid')) {
    broadcastSettingsUpdatedByGuid($groupGuid, $updates, $user_guid);
}

sendResponse(
    true,
    [
        'group_guid' => $groupGuid,
        'group_name' => $updatedGroup['group_name'],
        'updated_at' => $updatedGroup['updated_at']
    ],
    "Group settings updated successfully",
    200
);