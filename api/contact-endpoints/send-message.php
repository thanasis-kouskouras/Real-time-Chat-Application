<?php
/* SEND CONTACT US MESSAGE ENDPOINT
 
 POST /api/contact?action=send
 
Request body:
{
  "nickname": "John",
  "surname": "Doe",
  "subject": "Question about...",
  "message": "Hello, I have a question..."
} */

//Rate limit (max 5 contact messages per hour per user)
checkRateLimit($user_guid, 'contact_send', 5, 3600);

//Get input data
$input = getInput();

$nickname = trim(sanitizeString($input['nickname'] ?? ''));
$surname = trim(sanitizeString($input['surname'] ?? ''));
//Subject goes into email header (as plain text), not HTML/strip tags and prevent header injection
$subject = str_replace(["\r", "\n", "\0"], '', strip_tags(trim($input['subject'] ?? '')));
$message = trim(sanitizeString($input['message'] ?? ''));

//Validate required fields
if (empty($subject) && empty($message)) {
    sendError('Subject and message are required', 400, ['subject' => 'Subject is required', 'message' => 'Message is required']);
}

if (empty($subject)) {
    sendError('Subject is required', 400, ['subject' => 'Subject is required']);
}

if (empty($message)) {
    sendError('Message is required', 400, ['message' => 'Message is required']);
}

//Validate field lengths
if (strlen($nickname) > 50) {
    sendError('Nickname must be 50 characters or less', 400, ['nickname' => 'Nickname must be 50 characters or less']);
}

if (strlen($surname) > 50) {
    sendError('Surname must be 50 characters or less', 400, ['surname' => 'Surname must be 50 characters or less']);
}

if (strlen($subject) > 100) {
    sendError('Subject must be 100 characters or less', 400, ['subject' => 'Subject must be 100 characters or less']);
}

if (strlen($message) > 1000) {
    sendError('Message must be 1000 characters or less', 400, ['message' => 'Message must be 1000 characters or less']);
}

//Validate optional nickname and surname
$hasNickname = !empty($nickname);
$hasSurname = !empty($surname);
$nicknameInvalid = $hasNickname && invalidNickname($nickname);
$surnameInvalid = $hasSurname && invalidSurname($surname);

if ($nicknameInvalid && $surnameInvalid) {
    sendError('Invalid nickname and surname', 400, ['nickname' => 'Invalid nickname format', 'surname' => 'Invalid surname format']);
}

if ($nicknameInvalid) {
    sendError('Invalid nickname. Only letters are allowed', 400, ['nickname' => 'Invalid nickname format']);
}

if ($surnameInvalid) {
    sendError('Invalid surname. Only letters are allowed', 400, ['surname' => 'Invalid surname format']);
}

//Build sender name
$senderName = '';
if ($hasNickname && $hasSurname) {
    $senderName = " named $nickname $surname";
} elseif ($hasNickname) {
    $senderName = " named $nickname";
} elseif ($hasSurname) {
    $senderName = " named $surname";
}

//Build email body with HTML formatting
$body = "
<html>
<body style='font-family: Arial, sans-serif;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;'>
            New Contact Form Message
        </h2>
        <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>From:</strong> " . htmlspecialchars($userEmail) . "$senderName</p>
            <p style='margin: 0 0 10px 0;'><strong>Username:</strong> " . htmlspecialchars($userUsername) . "</p>
            <p style='margin: 0 0 10px 0;'><strong>User GUID:</strong> " . htmlspecialchars($user_guid) . "</p>
        </div>
        <div style='margin: 20px 0;'>
            <p style='margin: 0 0 10px 0;'><strong>Message:</strong></p>
            <div style='background-color: #ffffff; padding: 15px; border-left: 4px solid #007bff; border-radius: 3px; border: 1px solid #e9ecef;'>
                " . nl2br(htmlspecialchars($message)) . "
            </div>
        </div>
        <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
        <p style='color: #666; font-size: 12px; text-align: center;'>
            This message was sent via the EasyTalk Contact Form.
        </p>
    </div>
</body>
</html>";

//Send email using centralized function
$to = MAIL_USERNAME;
$from = $userEmail;
$fromName = $hasNickname || $hasSurname ? trim("$nickname $surname") : $userUsername;

list($success, $error) = send_email($to, $from, $fromName, $subject, $body);

if (!$success || $error !== "") {
    sendError('Failed to send message. Please try again later.', 500);
}

//Success
sendResponse(true, null, 'Message sent successfully!', 200);