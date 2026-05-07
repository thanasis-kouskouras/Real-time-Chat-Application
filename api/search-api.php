<?php
/* SEARCH API ROUTER

Routes search requests to appropriate endpoint handlers. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php';

//Set JSON response header
header('Content-Type: application/json');

//Get authenticated user
$user = requireApiAuthentication();
$user_guid = $user['user_guid'];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

//Route requests to appropriate endpoint files
try {
    switch ($action) {
        case 'search-users':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/search-endpoints/search-users.php';
            break;
            
        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    app_log("Search API Error: " . $e->getMessage());
    sendError('An error occurred', 500);
}