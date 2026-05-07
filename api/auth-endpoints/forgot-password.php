<?php
/* FORGOT PASSWORD ANDPOINT

 POST /api/auth?action=forgot-password
 
  Request body:
 {
   "user_email": "user@example.com"
 } */

$input = getInput();

//Validate CSRF token
$csrfToken = $input['csrftoken'] ?? '';
if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    sendError('Invalid request', 403);
}

$userEmail = sanitizeEmail($input['user_email'] ?? '');

//Apply rate limiting (3 forgot password requests per hour per email)
if (!empty($userEmail)) {
    checkRateLimit($userEmail, 'forgot_password', 3, 3600);
}

//Validate email
if (empty($userEmail)) {
    sendError('email address is required', 400, ['user_email' => 'email is required']);
}

if (!validateEmail($userEmail)) {
    sendError('Invalid email address', 400, ['user_email' => 'Invalid email format']);
}

//Check if user exists
list($rowData, $error) = getUserByEmail($userEmail);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($rowData)) {
    //Do not reveal whether the email exists (return success silently)
    sendResponse(true, null, 'If an account with that email exists, a reset link has been sent.', 200);
}

//Delete any existing password reset tokens
list($deleted, $error) = deletePasswordReset($userEmail);

if ($error !== "") {
    sendError($error, 500);
}

//Create new password reset token
try {
    list($selector, $rawToken, $error) = createPasswordReset($userEmail);
} catch (Exception $e) {
    app_log($e->getMessage());
    sendError('Failed to create password reset token', 500);
}

if ($error !== "") {
    sendError($error, 500);
}

if ($selector === false || $rawToken === false) {
    sendError('Failed to create password reset token', 500);
}

//Send reset email with selector and raw token
list($emailSent, $error) = reset_email($userEmail, $selector, $rawToken);

if ($error !== "") {
    sendError($error, 500);
}

if (!$emailSent) {
    sendError('Failed to send reset email', 500);
}

sendResponse(true, null, 'Password reset email sent successfully. Please check your inbox.', 200);