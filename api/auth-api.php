<?php
/* AUTHENTICATION API ENDPOINTS

Handles all HTTP API requests for authentication functionality (Login, Register, Logout, Forgot Password, Reset Password, Email Verification). */


require_once __DIR__ . '/../config.php';

//Start session for authentication state and redirects
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/api-response.php';
require_once __DIR__ . '/../includes/functions.inc.php';

//Set JSON response header
header('Content-Type: application/json');

//Get request method
$method = $_SERVER['REQUEST_METHOD'];

//Get action parameter
$action = $_GET['action'] ?? '';

//Route requests based on action
try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/login.php';
            break;
            
        case 'register':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/register.php';
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/logout.php';
            break;
            
        case 'forgot-password':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/forgot-password.php';
            break;
            
        case 'reset-password':
            if ($method !== 'POST') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/reset-password.php';
            break;
            
        case 'verify-email':
            if ($method !== 'POST' && $method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/verify-email.php';
            break;

        case 'email-redirect':
            if ($method !== 'GET') {
                sendError('Method not allowed', 405);
            }
            require_once __DIR__ . '/auth-endpoints/email-redirect.php';
            break;

        default:
            sendError('Invalid action specified', 400);
    }
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError('An error occurred', 500);
}