<?php
/* EMAIL REDIRECT ENDPOINT

GET /api/auth-api.php?action=email-redirect&target={page}

Handles redirects from email links to ensure proper login flow.
Allowed targets: messages, notifications, settings, index. */

//Get the target page from query parameters
$target = $_GET['target'] ?? 'messages';

//Validate target to prevent open redirects
$allowedTargets = [
    'messages' => 'messages.php',
    'notifications' => 'notifications.php',
    'settings' => 'settings.php',
    'index' => 'index.php'
];

//Normalize target (remove .php if present)
$target = str_replace('.php', '', $target);

//Default to messages if invalid target
if (!isset($allowedTargets[$target])) {
    $target = 'messages';
}

$redirectPage = $allowedTargets[$target];
$appPath = defined('APP_PATH') ? APP_PATH : '';

//Check if user is already logged in
list($user) = is_user_logged_in();

if ($user !== false && is_array($user)) {
    //Already logged in (redirect directly to target page)
    header("Location: " . rtrim($appPath, '/') . '/' . $redirectPage);
    exit();
}

//Not logged in (store redirect in session and send to login page)
$_SESSION['redirect_after_login'] = $redirectPage;
header("Location: " . rtrim($appPath, '/') . '/login.php?redirect=' . urlencode($redirectPage));
exit();