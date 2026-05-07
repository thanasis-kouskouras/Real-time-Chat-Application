<?php
/* VERIFY EMAIL ENDPOINT
 
GET/POST /api/auth?action=verify-email
 
Query params (GET):
?code=verification_code
 
Request body (POST):
{
  "code": "verification_code"
}
OR for resending verification:
{
  "user_email": "user@example.com",
  "resend": true
} */

//IP-based rate-limit for code-submission attempts (20/15 min)
$rateLimitCodeAttempt = function (): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit($ip, 'verify_email_attempt', 20, 900);
};

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rateLimitCodeAttempt();

    //Handle GET request with code in query string
    $code = sanitizeString($_GET['code'] ?? '');

    if (empty($code)) {
        sendError('Verification code is required', 400);
    }

    $isValid = validateVerificationTokenByGuid($code);
    
    if ($isValid) {
        sendResponse(true, null, 'Your email has been verified successfully. You can now login.', 200);
    } else {
        sendError('Invalid or expired verification code', 400);
    }
    
} else if ($method === 'POST') {
    
    $input = getInput();
    
    //Check if this is a resend request
    if (isset($input['resend']) && $input['resend']) {
        $email = sanitizeEmail($input['user_email'] ?? '');
        
        if (empty($email)) {
            sendError('Email address is required', 400);
        }
        
        if (!emailExists($email)) {
            sendError('Email address not found', 404);
        }
        
        if (isVerified($email)) {
            sendError('Email is already verified', 400);
        }

        //Rate limit verification-resend per email (3/hour)
        checkRateLimit($email, 'verify_resend', 3, 3600);

        //Generate and send new verification link
        list($success, $error) = generate_verification_link($email);
        
        if ($error !== "") {
            sendError($error, 500);
        }
        
        if ($success) {
            sendResponse(true, null, 'Verification email has been resent. Please check your inbox.', 200);
        } else {
            sendError('Failed to send verification email', 500);
        }
        
    } else {
        //Regular verification with code
        $rateLimitCodeAttempt();

        $code = sanitizeString($input['code'] ?? '');

        if (empty($code)) {
            sendError('Verification code is required', 400);
        }

        $isValid = validateVerificationTokenByGuid($code);
        
        if ($isValid) {
            sendResponse(true, null, 'Your email has been verified successfully. You can now login.', 200);
        } else {
            sendError('Invalid or expired verification code', 400);
        }
    }
}