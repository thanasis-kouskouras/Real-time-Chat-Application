<?php
/* LOGIN ENDPOINT

 POST /api/auth?action=login
 
 Request body:
 {
   "user_email": "user@example.com",
   "user_password": "password123",
   "remember_me": true
 } */

$input = getInput();

//Validate CSRF token
$csrfToken = $input['csrftoken'] ?? '';
if (empty($csrfToken) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    sendError('Invalid request', 403);
}

$email = sanitizeEmail($input['user_email'] ?? '');
$password = $input['user_password'] ?? '';
$remember_me = isset($input['remember_me']) && $input['remember_me'];

/* Apply rate limiting: 5 login attempts per 15 minutes per email.
Use email as identifier since user is not authenticated yet. */
if (!empty($email)) {
    checkRateLimit($email, 'login', 5, 900);
}

//Reject excessively long passwords before any processing
if (strlen($password) > 128) {
    sendError('Invalid credentials', 400);
}

//Validate inputs
if (emptyInputLogin($email, $password)) {
    sendError('email and password are required', 400, [
        'user_email' => empty($email) ? 'email is required' : null,
        'user_password' => empty($password) ? 'Password is required' : null
    ]);
}

//Attempt login
list($user, $error, $statusCode) = core_login($email, $password, $remember_me);

//Handle any errors from core_login
if ($error !== "" || !is_array($user)) {
    $errorMessage = $error !== "" ? $error : 'Invalid email or password';
    sendError($errorMessage, $statusCode);
}

//Check if there's a redirect URL from session
$redirectUrl = null;
if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
    $redirectUrl = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']); //Clear it after using
}

/* The web app itself uses only 'user' and 'redirect' from this response.
The 'jwt', 'jwt_expiry', 'remember_token', 'remember_token_expiry' are included for third-party/non-browser clients that cannot use cookies and need the tokens directly from the response body. */
sendResponse(true, [
    'user' => [
        'user_guid' => $user['user_guid'],
        'user_username' => $user['user_username'],
        'user_email' => $user['user_email'],
        'user_role' => $user['user_role'] ?? 'user'
    ],
    'jwt' => $user['jwt'] ?? null,
    'jwt_expiry' => $user['jwtTime'] ?? null,
    'remember_token' => $user['rememberToken'] ?? null,
    'remember_token_expiry' => $user['rememberTokenTime'] ?? null,
    'redirect' => $redirectUrl
], 'Login successful', 200);