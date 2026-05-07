<?php
/* REGISTER ENDPOINT

 POST /api/auth?action=register

 Request body:
 {
   "csrftoken": "abc123...",
   "user_username": "johndoe",
   "user_email": "john@example.com",
   "user_password": "password123",
   "confirm_password": "password123"
 } */

//Get input data
$input = getInput();

//Validate CSRF token
$csrfToken = $input['csrftoken'] ?? '';
if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    sendError('Invalid request', 403);
}

$username = sanitizeString($input['user_username'] ?? '');
$email = sanitizeEmail($input['user_email'] ?? '');
$password = $input['user_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

/* Apply rate limiting: 3 registration attempts per hour per IP.
Use IP address as identifier since user is not registered yet. */
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
checkRateLimit($ipAddress, 'register', 3, 3600);

//Collect validation errors
$errors = [];

//Validate inputs
if (emptyInputSignup($username, $email, $password, $confirmPassword)) {
    if (empty($username)) $errors['user_username'] = 'Username is required';
    if (empty($email)) $errors['user_email'] = 'Email is required';
    if (empty($password)) $errors['user_password'] = 'Password is required';
    if (empty($confirmPassword)) $errors['confirm_password'] = 'Password confirmation is required';
    
    sendError('All fields are required', 400, $errors);
}

if (invalidUsername($username)) {
    sendError('Invalid username. Only letters, numbers and _- are allowed.', 400, ['user_username' => '']);
}

if (usernameTooLong($username, 30)) {
    $errors['user_username'] = 'Username cannot exceed 30 characters';
}

if (invalidEmail($email)) {
    $errors['user_email'] = 'Invalid email address';
}

if (empty($password) || invalidPassword($password)) {
    $errors['user_password'] = 'Invalid password. Must be at least 8 characters';
}

if (strlen($password) > 128) {
    $errors['user_password'] = 'Password cannot exceed 128 characters';
}

if (!passwordMatch($password, $confirmPassword)) {
    $errors['confirm_password'] = 'Passwords do not match';
}

if (usernameExists($username)) {
    sendError('This username is already taken. Please choose a different one.', 400, ['user_username' => '']);
}

if (emailExists($email)) {
    sendError('Could not complete registration. If you already have an account, please log in or reset your password.', 400, ['user_email' => '']);
}

//If there are validation errors, return them
if (!empty($errors)) {
    sendError('Validation failed', 400, $errors);
}

//Create user
list($token, $error) = createUser($username, $email, $password);

if ($error !== "") {
    sendError($error, 500);
}

sendResponse(true, [], 'Registration successful! Please check your email to verify your account.', 201);