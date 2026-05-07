<?php
/* SETTINGS API ENDPOINTS

Handles user settings management via API.
Routes requests to individual endpoint files. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php';

//Set JSON response header
header('Content-Type: application/json');

//Authenticate user using unified authentication system
$user = requireApiAuthentication();
$user_guid = $user['user_guid'];

//Get request method and action parameter
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

//Route requests based on action
try {
    switch ($action) {
        case 'get':
            require_once __DIR__ . '/settings-endpoints/get-settings.php';
            break;
            
        case 'update':
            require_once __DIR__ . '/settings-endpoints/update-settings.php';
            break;
            
        default:
            sendError('Invalid action specified', 400);
    }
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError('An error occurred', 500);
}