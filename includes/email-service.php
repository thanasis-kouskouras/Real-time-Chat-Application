<?php
/* EMAIL SERVICE FUNCTIONS

Email sending functionality using PHPMailer or native mail. */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db/users.php';
require_once __DIR__ . '/db/passwordReset.php';

// Get the base URL with the application path.
function getBaseUrlWithPath(): string
{
    $rootUrl = rtrim($GLOBALS['rootUrl'] ?? 'http://localhost/', '/');
    $appPath = APP_PATH;

    //Avoid double-appending if root URL already contains the app path
    if (!empty($appPath) && strpos($rootUrl, $appPath) !== false) {
        return $rootUrl;
    }

    return $rootUrl . $appPath;
}

function send_email($userEmail, $from, $fromName, $subject, $body): array
{
    if (USE_MAIL == 'Native')
        return sendMailViaDefaultMail($userEmail, $subject, $body);
    else
        return sendMailWithPhpMailer($from, $fromName, $userEmail, $subject, $body);
}

function sendMailWithPhpMailer($from, $fromName, $userEmail, $subject, $body): array
{
    require __DIR__ . '/../vendor/autoload.php';
    $from = MAIL_USERNAME;
    $fromName = "EasyTalk";
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host     = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = (MAIL_ENCRYPTION === 'ssl')
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;
    $mail->CharSet = 'UTF-8';
    
    if (APP_DEBUG) {
        //Disable SSL verification only in development (XAMPP lacks CA certs)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
    }
    $mail->setFrom($from, $fromName);
    $mail->addAddress($userEmail);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;

    $isSend = $mail->send();

    return [$isSend, ""];
}

function sendMailViaDefaultMail($to, $subject, $body): array
{
    $headers = "From: EasyTalk <" . MAIL_USERNAME . ">\r\n";
    $headers .= "Reply-to: " . MAIL_USERNAME . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
    
    mail($to, $subject, $body, $headers);
    return [true, ""];
}

function verify_email($userEmail, $code): array
{
    $webAddress = getBaseUrlWithPath();
    $from = MAIL_USERNAME;
    $fromName = 'Easytalk';
    $subject = 'Registration Verification for EasyTalk Chat Application';

    $body = '
            <p>Thank you for registering for EasyTalk Chat Application.</p>
                <p>This is a verification email, please click the link to verify your email address.</p>
                <p><a href="' . rtrim($webAddress, '/') . '/verify.php?code=' . $code . '">Click to Verify</a></p>
            ';

    //Check if email was sent
    list($emailSent, $emailError) = send_email($userEmail, $from, $fromName, $subject, $body);
    
    if (!$emailSent) {
        $errorMessage = 'Failed to send verification email. ' . ($emailError ?: 'Please try again later.');
        app_log("Verification email failed for $userEmail: $errorMessage");
        return [false, $errorMessage];
    }
    
    $success_message = 'Verification email sent to ' . $userEmail . ', so before login first verify your email';
    $_SESSION['message'] = $success_message;
    return [true, ""];
}

function reset_email($userEmail, string $selector, string $rawToken): array
{
    $webAddress = getBaseUrlWithPath();
    $url = rtrim($webAddress, '/') . "/forgot-password-reset.php?selector=" . urlencode($selector) . "&validator=" . bin2hex($rawToken);
    $subject = "EasyTalk: Forgot password?";
    $body = "<p>EasyTalk received a forgotten password reset request. The reset password link is below. If you did not make this request, you could ignore this email.\n
    </p><p>Here is your reset password link: <br><a href='" . $url . "'>" . $url . "</a></p>";

    $from = MAIL_USERNAME;
    $fromName = 'Easytalk';
    send_email($userEmail, $from, $fromName, $subject, $body);
    $success_message = 'Reset Password email sent to ' . $userEmail . ', so before login first reset your password in the received email';
    $_SESSION['message'] = $success_message;
    return [true, ""];
}

function generate_verification_link($email): array
{
    list($user, $error) = getUserByEmail($email);
    if ($error !== "")
        return array(false, $error);
    list($success, $error) = reset_verification_token($email);
    if ($error !== "")
        return array(false, $error);
    list($success, $error) = verify_email($email, $user['user_verification_token']);
    if ($error !== "")
        return array(false, $error);
    return [true, ""];
}

function sendMessageNotificationEmail($user_guid, $senderName, $messageContent, $senderGuid = null)
{
    list($user, $error) = getUserByGuid($user_guid);
    if ($error !== "" || !shouldSendEmailNotificationByGuid($user_guid)) {
        return false;
    }

    if ($senderGuid !== null && !shouldSendEmailFromSenderByGuid($user_guid, $senderGuid)) {
        echo "Email throttled for user $user_guid from sender $senderGuid (already sent while offline)\n";
        return false;
    }

    $baseUrl = rtrim(getBaseUrlWithPath(), '/');
    $replyUrl = $baseUrl . "/api/auth-api.php?action=email-redirect&target=messages";
    $settingsUrl = $baseUrl . "/api/auth-api.php?action=email-redirect&target=settings";

    $subject = "New message from $senderName - EasyTalk";
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px;'>
                You have a new message on EasyTalk!
            </h2>
            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 0 0 10px 0;'><strong>From:</strong> " . htmlspecialchars($senderName) . "</p>
                <p style='margin: 0 0 10px 0;'><strong>Message:</strong></p>
                <div style='background-color: #ffffff; padding: 15px; border-left: 4px solid #007bff; border-radius: 3px; margin: 10px 0;'>
                    " . nl2br(htmlspecialchars($messageContent)) . "
                </div>
            </div>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . $replyUrl . "'
                   style='background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                     Reply on EasyTalk
                </a>
            </div>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                You received this email because you have email notifications enabled in your EasyTalk settings.<br>
                <a href='" . $settingsUrl . "' style='color: #007bff;'>Manage your notification preferences</a>
            </p>
        </div>
    </body>
    </html>";

    $from = MAIL_USERNAME;
    $fromName = 'EasyTalk';

    list($success, $error) = send_email($user['user_email'], $from, $fromName, $subject, $body);
    
    if ($success && $senderGuid !== null) {
        markEmailSentFromSenderByGuid($user_guid, $senderGuid);
    }
    
    return $success;
}

function sendFriendRequestNotificationEmail($user_guid, $senderName)
{
    list($user, $error) = getUserByGuid($user_guid);
    if ($error !== "" || !shouldSendEmailNotificationByGuid($user_guid)) {
        return false;
    }

    try {
        $baseUrl = rtrim(getBaseUrlWithPath(), '/');
        $notificationsUrl = $baseUrl . "/api/auth-api.php?action=email-redirect&target=notifications";

        $subject = "New friend request from $senderName - EasyTalk";
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #333;'>You have a new friend request on EasyTalk!</h2>
                <p><strong>" . htmlspecialchars($senderName) . "</strong> wants to be your friend.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $notificationsUrl . "'
                       style='background-color: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>
                        Manage Friends
                    </a>
                </div>
            </div>
        </body>
        </html>";

        $from = MAIL_USERNAME;
        $fromName = 'EasyTalk';

        list($success, $error) = send_email($user['user_email'], $from, $fromName, $subject, $body);
    } catch (Exception $e) {
        return false;
    }
    return $success;
}