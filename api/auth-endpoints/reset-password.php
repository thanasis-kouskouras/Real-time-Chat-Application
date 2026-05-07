<?php
/* RESET PASSWORD ENDPOINT

 POST /api/auth?action=reset-password
 
 Request body:
 {
  "selector": "abc123...",
  "validator": "def456...",
  "user_password": "newpassword123",
  "confirm_password": "newpassword123"
 } */

//Get input data
$input = getInput();

//Validate CSRF token
$csrfToken = $input['csrftoken'] ?? '';
if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    sendError('Invalid CSRF token', 403);
}

$selector = sanitizeString($input['selector'] ?? '');
$validator = sanitizeString($input['validator'] ?? '');
$password = $input['user_password'] ?? '';
$passwordRepeat = $input['confirm_password'] ?? '';

//Collect validation errors
$errors = [];

//Validate inputs
if (empty($selector) || empty($validator)) {
    sendError('Invalid reset link', 400);
}

if (empty($password)) {
    $errors['user_password'] = 'Password is required';
}

if (empty($passwordRepeat)) {
    $errors['confirm_password'] = 'Password confirmation is required';
}

if (!empty($password) && invalidPassword($password)) {
    $errors['user_password'] = 'Invalid password';
}

if (!empty($password) && strlen($password) < 8) {
    $errors['user_password'] = 'Password must be at least 8 characters';
}

if (!empty($password) && strlen($password) > 128) {
    $errors['user_password'] = 'Password cannot exceed 128 characters';
}

if (!empty($password) && !empty($passwordRepeat) && $password !== $passwordRepeat) {
    $errors['confirm_password'] = 'Passwords do not match';
}

if (!empty($errors)) {
    sendError('Validation failed', 400, $errors);
}

//Rate limiting (5 attempts per 15 minutes per selector)
checkRateLimit($selector, 'reset_password', 5, 900);

//Check if reset token is valid and not expired
$currentDate = date("U");
$tokenBin = hex2bin($validator);

list($row, $error) = getPasswordResetBySelectorAndDate($selector, $currentDate);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($row) || $row == null) {
    sendError('Reset link has expired or is invalid', 400);
}

//Verify raw token against stored bcrypt hash
if (!password_verify($tokenBin, $row["token"])) {
    sendError('Invalid reset token', 400);
}

//Get user by email from reset token
$tokenEmail = $row["email"];

list($rowData, $error) = getUserByEmail($tokenEmail);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($rowData)) {
    sendError('User not found', 404);
}

//Change password
list($success, $error) = changePasswordByGuid($password, $rowData['user_guid']);

if ($error !== "") {
    sendError($error, 500);
}

if (!$success) {
    sendError('Failed to update password', 500);
}

//Delete the used reset token
list($deleted, $error) = deletePasswordReset($tokenEmail);

if ($error !== "") {
    //Log error but don't fail the request since password was changed
    app_log("Failed to delete password reset token: $error");
}

sendResponse(true, null, 'Password has been reset successfully. You can now login with your new password.', 200);