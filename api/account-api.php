<?php
/* ACCOUNT MANAGEMENT API ENDPOINT
 
Handles account-related actions.
Requires authentication via JWT or remember_me cookie. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/websocket_notifications.php';

header('Content-Type: application/json'); //Set JSON response header
$user = requireApiAuthentication(); //Authenticate user using unified authentication system
$user_guid = $user['user_guid'];
$method = $_SERVER['REQUEST_METHOD']; //Get request method
$action = $_GET['action'] ?? ''; //Get action parameter

//Route requests based on action
try {
    switch ($action) {
        case 'update-username':
            if ($method !== 'PUT' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/update-username.php';
            break;
            
        case 'update-password':
            if ($method !== 'PUT' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/update-password.php';
            break;
            
        case 'upload-image':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/upload-image.php';
            break;
            
        case 'delete-image':
            if ($method !== 'DELETE' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/delete-image.php';
            break;
            
        case 'delete-account': 
            if ($method !== 'DELETE' && $method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/delete-account.php';
            break;
            
        case 'get-image':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/get-image.php';
            break;
        case 'get':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/account-endpoints/get-account.php';
            break;

        default:
            sendError("Invalid action specified", 400);
    }
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError("An error occurred", 500);
}