<?php
/* UPDATE USER SETTINGS ENDPOINT

Updates user settings with validation and rate limiting. */

require_once __DIR__ . '/../../includes/user-service.php';

//Only allow POST and PUT requests
if ($method !== 'POST' && $method !== 'PUT') {
    sendError('Method not allowed', 405);
}

//Get input data
$input = getInput();

//Validate that at least one setting is provided
$allowedSettings = ['hide_account_from_search', 'email_notifications'];
$settingsToUpdate = [];

foreach ($allowedSettings as $setting) {
    if (isset($input[$setting])) {
        //Convert boolean to integer for database storage
        $settingsToUpdate[$setting] = $input[$setting] ? 1 : 0;
    }
}

if (empty($settingsToUpdate)) {
    sendError('No valid settings provided', 400);
}

//Apply rate limiting for settings updates
checkRateLimit($user_guid, 'update_settings', 10, 60); //10 updates per minute

//Save settings
$success = saveUserSettingsByGuid($user_guid, $settingsToUpdate);

if (!$success) {
    sendError('Failed to save settings', 500);
}

//Get updated settings to return
$updatedSettings = getUserSettingsByGuid($user_guid);

//Ensure default values and convert to boolean
$defaultSettings = [
    'hide_account_from_search' => 0,
    'email_notifications' => 0
];

$updatedSettings = array_merge($defaultSettings, $updatedSettings);

$apiSettings = [
    'hide_account_from_search' => (bool)$updatedSettings['hide_account_from_search'],
    'email_notifications' => (bool)$updatedSettings['email_notifications']
];

sendSuccessResponse(['settings' => $apiSettings], 'Settings updated successfully');