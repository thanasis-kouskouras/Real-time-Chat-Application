<?php
/* FRIEND MANAGEMENT API ENDPOINT
 
Handles all HTTP API requests for friend functionality. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php'; // Unified authentication system

//Set JSON response header
header('Content-Type: application/json');

//Authenticate user using unified authentication system
$user = requireApiAuthentication();

$user_guid = $user['user_guid'];

//Get request method
$method = $_SERVER['REQUEST_METHOD'];

//Get action parameter
$action = $_GET['action'] ?? '';

//Route requests based on action
try {
    switch ($action) {
        case 'add':
            require_once __DIR__ . '/friend-endpoints/add-friend.php';
            break;
            
        case 'accept':
            require_once __DIR__ . '/friend-endpoints/accept-friend.php';
            break;
            
        case 'reject':
            require_once __DIR__ . '/friend-endpoints/reject-friend.php';
            break;
            
        case 'delete':
            require_once __DIR__ . '/friend-endpoints/delete-friend.php';
            break;
            
        case 'cancel':
            require_once __DIR__ . '/friend-endpoints/cancel-request.php';
            break;
            
        case 'get-friends':
            require_once __DIR__ . '/friend-endpoints/get-friends.php';
            break;
            
        case 'get-pending-notifications':
            require_once __DIR__ . '/friend-endpoints/get-pending-notifications.php';
            break;
            
        case 'verify':
            require_once __DIR__ . '/friend-endpoints/verify-friendship.php';
            break;

        case 'validate-members-for-group':
            require_once __DIR__ . '/friend-endpoints/validate-members-for-group.php';
            break;

        default:
            sendError("Invalid action specified", 400);
    }
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError("An error occurred", 500);
}