<?php
/* CONTACT US API ENDPOINT

Handles contact form submissions. */

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.inc.php';

//Set JSON response header
header('Content-Type: application/json');

//Authenticate user
$user = requireApiAuthentication();
$user_guid = $user['user_guid'];
$userUsername = $user['user_username'];
$userEmail = $user['user_email'];

//Get request method
$method = $_SERVER['REQUEST_METHOD'];

//Get action parameter
$action = $_GET['action'] ?? '';

//Route requests based on action
try {
    switch ($action) {
        case 'send':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/contact-endpoints/send-message.php';
            break;
            
        default:
            sendError("Invalid action specified", 400);
    }
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError("An error occurred", 500);
}