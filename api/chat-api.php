<?php
/* CHAT API ROUTER

Routes chat-related requests to appropriate endpoint handlers. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php';

//Set JSON response header
header('Content-Type: application/json');

//Get authenticated user
$user = requireApiAuthentication();

//Get user GUID for API operations
try {
    if (!isset($user['user_guid']) || !$user['user_guid']) {
        throw new RuntimeException("User GUID not found in authentication data");
    }
    $user_guid = $user['user_guid'];
} catch (Exception $e) {
    app_log("Chat API - GUID lookup failed: " . $e->getMessage());
    sendError("Authentication error", 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

//Route requests to appropriate endpoint files
try {
    switch ($action) {
        case 'get-unread-messages':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/chat-endpoints/get-unread-messages.php';
            break;
            
        case 'get-messages':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require __DIR__ . '/chat-endpoints/get-messages.php';
            break;
            
        default:
            sendError('Invalid action', 400);
    }
} catch (Exception $e) {
    app_log("Chat API Error: " . $e->getMessage());
    sendError('An error occurred', 500);
}