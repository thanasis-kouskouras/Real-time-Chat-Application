<?php
/*UPDATE USERNAME ENDPOINT

PUT/POST /api/account-api.php?action=update-username
  
Request body:
{
  "new_username": "johndoe123"
} */

require_once __DIR__ . '/../../includes/functions.inc.php';

checkRateLimit($user_guid, 'update_username', 10, 3600); //Rate limit (max 10 username changes per hour)
$input = getInput(); //Get input data
$newUsername = sanitizeString($input['new_username'] ?? '');

//Validate input
if (emptyInputNewUsername($newUsername)) {
    sendError('New username is required', 400, ['new_username' => '']);
}

if (invalidUsername($newUsername)) {
    sendError('Invalid username. Only letters, numbers and _- are allowed.', 400, ['new_username' => '']);
}

if (usernameTooLong($newUsername, 30)) {
    sendError('Username cannot exceed 30 characters', 400, ['new_username' => '']);
}

if (usernameExists($newUsername)) {
    sendError('This username is already taken. Please choose a different one.', 400, ['new_username' => '']);
}

//Update username
list($success, $error) = changeUsernameByGuid($newUsername, $user_guid);
if ($error !== "" || !$success) {
    sendError($error ?: 'Failed to update username', 500);
}

sendResponse(true, [
    'user_username' => $newUsername
], 'Username updated successfully', 200);