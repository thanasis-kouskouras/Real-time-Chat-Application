<?php
/* GET USER SETTINGS ENDPOINT
 
Retrieves user settings with default values. */

require_once __DIR__ . '/../../includes/user-service.php';

//Only allow GET requests
if ($method !== 'GET') {
    sendError('Method not allowed', 405);
}

//Get user settings
$settings = getUserSettingsByGuid($user_guid);

//Ensure default values for expected settings
$defaultSettings = [
    'hide_account_from_search' => 0,
    'email_notifications' => 0
];

$settings = array_merge($defaultSettings, $settings);

//Convert to boolean for API response
$apiSettings = [
    'hide_account_from_search' => (bool)$settings['hide_account_from_search'],
    'email_notifications' => (bool)$settings['email_notifications']
];

sendSuccessResponse(['settings' => $apiSettings], 'Settings retrieved successfully');