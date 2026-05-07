<?php
/* UPDATE GROUP NAME ENDPOINT

Handles POST requests for updating group name. */

require_once(dirname(__FILE__) . '/../../includes/websocket_notifications.php');

//Verify POST method
if ($method !== 'POST') {
    sendError("Method not allowed. Use POST.", 405);
}

//Rate limit (max 10 renames per hour)
checkRateLimit($user_guid, 'update_group_name', 10, 3600);

//Get group GUID from URL query parameter
$groupGuid = isset($_GET['group_guid']) ? trim($_GET['group_guid']) : '';

$input = getInput();
$groupName = isset($input['group_name']) ? trim($input['group_name']) : '';

//Validate inputs
if (empty($groupGuid)) {
    sendError("Group GUID is required", 400);
}

if (!isValidGuid($groupGuid)) {
    sendError('Invalid group GUID format', 400);
}

//Validate and sanitize group name using the same function as create-group
$groupName = validateGroupName($groupName);

//Verify user is group admin
if (!isGroupAdminByGuid($groupGuid, $user_guid)) {
    sendError('Only group admins can rename the group', 403);
}

//Update group name
list($result, $updateError) = updateGroupChatByGuid($groupGuid, ['group_name' => $groupName]);

if (!$result) {
    sendError($updateError ?: "Failed to update group name", 500);
}

//Broadcast settings updated notification
if (function_exists('broadcastSettingsUpdatedByGuid')) {
    broadcastSettingsUpdatedByGuid($groupGuid, ['group_name' => $groupName], $user_guid);
}

//Return success response
sendResponse(true, [
    'group_guid' => $groupGuid,
    'group_name' => $groupName
], 'Group name updated successfully', 200);