<?php
/* UPDATE PASSWORD ENDPOINT

PUT/POST /api/account-api.php?action=update-password

Request body:
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "confirm_password": "newpassword123"
} */

require_once __DIR__ . '/../../includes/functions.inc.php';

checkRateLimit($user_guid, 'update_password', 5, 900); //Rate limit (max 5 password changes per 15 minutes)

$input = getInput();
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

$errors = []; //Collect validation errors

//Validate inputs (check for empty fields first)
if (empty($currentPassword)) {
    $errors['current_password'] = 'Current password is required';
}
if (empty($newPassword)) {
    $errors['new_password'] = 'New password is required';
}
if (empty($confirmPassword)) {
    $errors['confirm_password'] = 'Password confirmation is required';
}

//If any fields are empty, return error
if (!empty($errors)) {
    sendError('All fields are required', 400, $errors);
}

//Validate new password length
if (strlen($newPassword) < 8) {
    sendError('New password must be at least 8 characters', 400, ['new_password' => 'Password must be at least 8 characters']);
}
if (strlen($newPassword) > 128) {
    sendError('New password is too long', 400, ['new_password' => 'Password cannot exceed 128 characters']);
}

//Verify current password
if (!isPasswordCorrectByGuid($currentPassword, $user_guid)) {
    sendError('Current password is incorrect', 400, [
        'current_password' => ''
    ]);
}

//Validate new password
if (invalidPassword($newPassword)) {
    sendError('Invalid new password', 400, [
        'new_password' => 'Invalid password format'
    ]);
}

//Check if passwords match
if ($newPassword !== $confirmPassword) {
    sendError('Passwords do not match', 400, [
        'confirm_password' => 'Passwords do not match'
    ]);
}

//Update password
list($success, $error) = changePasswordByGuid($newPassword, $user_guid);

if ($error !== "") {
    sendError($error, 500);
}

if (!$success) {
    sendError('Failed to update password', 500);
}

sendResponse(true, null, 'Password updated successfully', 200);