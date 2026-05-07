<?php
/* ADMIN API ROUTER

Routes admin requests to appropriate endpoint handlers. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php';

//Set JSON response header
header('Content-Type: application/json');

//Get authenticated user
list($user, $error) = is_user_logged_in();

if (!$user) {
    sendError('Authentication required', 401);
}

//Check if user is admin
if ($user['user_role'] !== 'admin') {
    sendError('Admin access required', 403);
}

//Store admin GUID for use in endpoint files
$adminGuid = $user['user_guid'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

//Route requests to appropriate endpoint files
try {
    switch ($action) {
        case 'ban-user':
            if (!in_array($method, ['POST', 'PUT'])) {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/admin-endpoints/ban-user.php';
            break;

        case 'unban-user':
            if (!in_array($method, ['POST', 'PUT'])) {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/admin-endpoints/unban-user.php';
            break;
            
        case 'list-users':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/admin-endpoints/list-users.php';
            break;
            
        case 'search-users':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/admin-endpoints/search-users.php';
            break;

        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    app_log("Admin API Error: " . $e->getMessage());
    sendError('An error occurred', 500);
}