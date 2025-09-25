<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once('db/addrequest.php');
require_once('db/attachments.php');
require_once('db/users.php');
require_once('db/chatbox.php');
require_once('db/profileImage.php');
require_once('db/passwordReset.php');
require_once('db/usertokens.php');
require_once('db/_config.php');
require_once(__DIR__ . '/../config.php');

function emptyInputSignup($username, $email, $password, $passwordConfirm): bool
{

    if (empty($username) || empty($email) || empty($password) || empty($passwordConfirm)) {
        $result = true;
    } else {
        $result = false;
    }
    return $result;
}


function invalidNickname($nickname): bool
{

    if (!preg_match("/^[a-zA-Z]*$/", $nickname)) {
        $result = true;
    } else {
        $result = false;
    }
    return $result;
}


function invalidSurname($surname): bool
{

    if (!preg_match("/^[a-zA-Z]*$/", $surname)) {
        $result = true;
    } else {
        $result = false;
    }
    return $result;
}


function invalidUsername($username): bool
{

    if (!preg_match("/^[a-zA-Z\d_\-]*$/", $username)) {
        $result = true;
    } else {
        $result = false;
    }
    return $result;
}

function invalidPassword($password): bool
{
    if (!preg_match("/^[a-zA-Z\d_\-!@#\$%^&*()]*$/", $password)) {
        $result = true;
    } else {
        $result = false;
    }
    return $result;
}

function invalidEmail($email): bool
{

    // Remove all illegal characters from email
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $result = true;

    // Validate e-mail
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = false;
    }

    return $result;
}


function passwordMatch($password, $passwordConfirmed): bool
{
    return $password === $passwordConfirmed;
}


function emptyInputNewUsername($newUsername): bool
{
    $result = false;
    if (empty($newUsername))
        $result = true;
    return $result;
}

function emptyInputNewPassword($password, $newPassword, $newPasswordRepeat): bool
{
    $result = false;
    if (empty($password) || empty($newPassword) || empty($newPasswordRepeat))
        $result = true;
    return $result;
}

function isPasswordCorrect($password, $userid)
{
    list($user, $error) = getUserById($userid);
    if ($error !== "") {
        setSessionError($error);
        return false;
    }
    if (!idExists($userid)) {
        header("location: ../profile.php?error=stmtfailed");
        exit();
    }
    $passwordHashed = $user["usersPassword"];

    return password_verify($password, $passwordHashed);
}

function idExists($userid): bool
{
    list($user, $error) = getUserById($userid);
    if ($error == "" && is_array($user))
        return true;
    return false;
}


function emptyInputLogin($email, $password): bool
{

    if (empty($email) || empty($password)) {
        return true;
    }
    return false;
}


function emailExists($email): bool
{
    list($data, $error) = getUserByEmail($email);
    if ($error !== "" || !is_array($data)) {
        return false;
    }
    return true;
}

// remember me methods
function generateTokens(): array|bool
{
    try {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        echo $e;
        return false;
    }

    return [$selector, $validator, $selector . ':' . $validator];
}

function parse_token(string $token): ?array
{
    $parts = explode(':', $token);

    if ($parts && count($parts) == 2) {
        return [$parts[0], $parts[1]];
    }
    return null;
}

function tokenIsValid(string $token): bool
{
    // parse the token to get the selector and validator
    [$selector, $validator] = parse_token($token);
    list($tokens, $error) = getUserTokenBySelector($selector);
    if ($error !== "" || !$tokens) {
        return false;
    }
    return password_verify($validator, $tokens['hashed_validator']);
}

function rememberMe(int $user_id, int $interval = REMEMBER_ME_INTERVAL): array|bool
{
    [$selector, $validator, $token] = generateTokens();

    // remove all existing token associated with the user id
    list($result, $error) = deleteUserToken($user_id);
    if ($error != "" || !$result) {
        return false;
    }
    // set expiration date
    $expired_seconds = time() + $interval;

    // insert a token to the database
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);
    list($result, $error) = createUserToken($user_id, $selector, $hash_validator, $expiry);
    if ($error !== "" || !$result) {
        return false;
    }

    setcookie('remember_me', $token, $expired_seconds, '/');
    return array($token, $expired_seconds);
}

function is_user_logged_in(): bool|array
{
    // 1. check the jwt
    if (isset($_COOKIE['jwt'])) {
        $user = validate($_COOKIE["jwt"]);
        if ($user !== false)
            return [$user, ""];
    }
    // 2. check the remember_me in cookie
    $token = htmlspecialchars(filter_input(INPUT_COOKIE, 'remember_me'));

    if ($token && tokenIsValid($token)) {
        list($user, $error) = getUserByToken($token);
        if ($error !== "") {
            $_SESSION['error'] = $error; // add the error to session
            return [false, ""];
        }
        if (is_array($user))
            return [init_session($user), ""];
    }
    // 3. not logged in
    // start session even if no user logged in , because we give him errors from session array
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return [false, ""];
}

function is_user_logged_in_websocket(string|null $jwt, string|null $remember_me): bool|array
{
    // 1. check the jwt
    if ($jwt != null) {
        $user = validate($jwt);
        if ($user !== false)
            return [$user, ""];
    }
    // 2. check the remember_me in cookie
    if ($remember_me != null) {

        $token = htmlspecialchars($remember_me);

        if ($token && tokenIsValid($token)) {
            list($user, $error) = getUserByToken($token);
            if ($error !== "") {
                $_SESSION['error'] = $error; // add the error to session
                return [false, ""];
            }
            if (is_array($user))
                return [init_session($user), ""];
        }
    }
    
    return [false, ""];
}

function setSessionError($message): void
{

    $_SESSION['error'] = $message; // add the error to session
}

function logout($user_id): bool
{

    // delete the user token
    list($result, $error) = deleteUserToken($user_id);
    if ($error !== "") {
        setSessionError($error);
        return false;
    }

    // remove the remember_me cookie
    if (isset($_COOKIE['remember_me'])) {
        unset($_COOKIE['remember_me']);
        setcookie('remember_user', null, -1, '/');
    }
    if (isset($_COOKIE['jwt'])) {
        unset($_COOKIE['jwt']);
        setcookie('jwt', null, -1, '/');
    }
    // remove all session data
    session_destroy();

    return true;
}

// VALIDATE JWT
// RETURN USER IF VALID
// RETURN FALSE IF INVALID
function validate($jwt): bool|array
{
    require_once __DIR__ . "/../vendor/autoload.php";
    try {
        $jwt = Firebase\JWT\JWT::decode($jwt, new Firebase\JWT\Key(JWT_SECRET, JWT_ALGO));
        $valid = is_object($jwt);
    } catch (Firebase\JWT\ExpiredException|Firebase\JWT\SignatureInvalidException) {
        setSessionError("Your Session has expired. Please login again.");
        return false;
    }

    // GET USER
    if ($valid) {
        list($user, $error) = getUserById($jwt->data->id);
        if ($error !== "") {
            setSessionError($error);
            return false;
        }
        $valid = is_array($user);
    }

    // RETURN RESULT
    if ($valid) {
        unset($user["usersPassword"]);
        return $user;
    } else {
        return false;
    }
}

function generate_jwt($user_id): string
{
    require_once __DIR__ . "/../vendor/autoload.php";
    $now = strtotime("now");
    try {
        return Firebase\JWT\JWT::encode([
            "iat" => $now, // issued at - time when token is generated
            "nbf" => $now, // not before - when this token is considered valid
            "exp" => $now + JWT_TIME, // expiry - 1 hr (3600 secs) from now 
            "jti" => base64_encode(random_bytes(16)), // json token id
            "iss" => JWT_ISSUER, // issuer
            "aud" => JWT_AUD, // audience
            "data" => ["id" => $user_id] // whatever data you want to add
        ], JWT_SECRET, JWT_ALGO);
    } catch (Exception $e) {
        echo $e;
        setSessionError("An error has Occurred. Please try again.");
        return "";
    }
}

function init_session($user): array|bool
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION["UserID"] = $user["UsersID"];
    $_SESSION["userUid"] = $user["usersUsername"];
    $_SESSION["userEmail"] = $user["usersEmail"];
    $jwt = generate_jwt($user['UsersID']);
    $jwtCookieTime = time() + JWT_TIME;
    if ($jwt) {
        setcookie("jwt", $jwt, $jwtCookieTime, '/');
    } else
        return false;

    unset($user["usersPassword"]);
    $user['jwtTime'] = $jwtCookieTime;
    $user['jwt'] = $jwt;
    return $user;
}

function core_login($email, $password, $remember = false): bool|array
{
    $emailExists = emailExists($email);
    if (!$emailExists) //email not found
        return [false, "Email not found!Please try again!"];

    $isVerified = isVerified($email);
    if (!$isVerified) // user not verified
        return [false, "Email not verified!Please check your inbox for email verification link!
       <button type='button' onclick=regenerate_verify_email('$email') class='btn btn-success'>Click here</button> to resend a new verification link!"];

    list($user, $error) = getUserByEmail($email);
    if ($error !== "") { //user not found in db
        return [$user, $error];
    }

    if (isset($_SESSION["error"]) && $_SESSION["error"] !== "") {
        return [false, ""];
    }
    $valid = is_array($user);//maybe deleted
    if ($valid) {
        $valid = password_verify($password, $user['usersPassword']);
    }
    // RETURN JWT IF OK, FALSE IF NOT
    if ($valid) {
        if ($remember) {
            // if remember me was clicked the set the token
            list($token, $rememberTime) = rememberMe($user['UsersID']);
            $user['rememberToken'] = $token;
            $user['rememberTokenTime'] = $rememberTime;
        }
        return [init_session($user), ""];
    } else {
        return [false, "Wrong password. Please try again."];
    }
}


function isVerified($email): bool
{
    list($user, $error) = getUserByEmail($email);
    if ($error !== "") {
        setSessionError($error);
        return false;
    }
    if (is_array($user)) {
        if ($user['usersEmailVerified'] == 'True')
            return true;
    }
    return false;

}

function escapeString($input): string
{

    $conn = $GLOBALS['_mc'];
    return mysqli_real_escape_string($conn, $input);
}

function my_is_int($var): bool|int
{
    return preg_match('/^\d+$/', $var);
}


function getFileSavePath($fileGuid, $ext): string
{
    $base = $GLOBALS['baseFilePath'];
    if (!file_exists($base))
        mkdir($base, 0777, true);
    return $base . $fileGuid . "." . $ext;
}

function getAttachmentUrl($attachmentId): ?string
{
    if ($attachmentId == null)
        return null;
    list($metadata, $error) = getAttachmentById($attachmentId);
    if ($error !== "" || !is_array($metadata)) {
        setSessionError($error);
        return false;
    }
    return getRelativeDownloadPath() . $metadata['guid'];
}

function getAttachmentUrlAndMime($attachmentId): ?array
{
    if ($attachmentId == null)
        return array(null, null);
    list($metadata, $error) = getAttachmentById($attachmentId);
    if ($error !== "" || !is_array($metadata)) {
        setSessionError($error);
        return array(false, false);
    }
    return array(getRelativeDownloadPath() . $metadata['guid'], $metadata['mimetype']);
}

function getAttachmentDescription($attachmentId): string
{
    list($metadata, $error) = getAttachmentById($attachmentId);
    if ($error !== "" || !is_array($metadata)) {
        setSessionError($error);
        return false;
    }
    return $metadata['description'];
}


/**
 * @param $userUsername
 * @param array $rowType
 * @param mixed $rqstFromId
 * @return void
 */
function generateFriendsHtml($userUsername, array $rowType, mixed $rqstFromId): void
{
    // Fetch user status
    list($user, $error) = getUserById($rqstFromId);
    if ($error !== "") {
        setSessionError($error);
        return;
    }

    $isActive = isset($user['usersStatus']) && ($user['usersStatus'] === 'Active');
    $imgId = $isActive ? 'profileImgInFriendsActive' : 'profileImgInFriendsInactive';

    $imgHtml = "<img id='$imgId' data-friend-id='$rqstFromId' src='img/profiledefault.jpg' alt='profileImage'>";
    // Check if user has a profile image
    if (isset($rowType['imgType']) && isset($rowType['imgStatus'])) {
        $imgPath = "uploads/profile" . $rqstFromId . "." . $rowType['imgType'] . "?" . mt_rand();

        if ($rowType['imgType'] == "jpg" && $rowType['imgStatus'] == 0) {
            $imgHtml = "<img id='$imgId' src='$imgPath'>";
        } elseif ($rowType['imgType'] != "jpg") {
            $imgHtml = "<img id='$imgId' src='$imgPath'>";
        }
    }

    echo '<div class="form-group mb-0 d-flex justify-content-between">';
    echo "<div id='left'>";
    echo $imgHtml;
    echo "<strong id='black'> " . $userUsername . " </strong>";
    echo '</div>';
    echo '<div id="right">';
    echo '<div class="form-group mb-0 d-flex justify-content-between">';
    echo "<div id='left'";
    echo "<button id='buttonFriend' class='btn btn-danger' onclick=friendManager('delete',{$rqstFromId},false,'','friends.php') name='deletefriend'>Delete</button>";
    echo '</div>';
    echo '<i> </i>';
    echo '<div id="friendDiv">';
    echo "<button id='buttonFriend' class='btn btn-primary' onclick=friendManager('chat',{$rqstFromId}) name='chat' >Chat</button>";
    echo '</div></div>';
    echo '</div></div>';
}

/**
 * @param $id
 * @return int
 */
function getNotifyCounter($id): int
{
    // unused
    $not = count(getPendingNotifications($id));
    $chatsD = count(getUnDeliveredChat($id));
    $chatsR = count(getUnReadChat($id));
    $result = $not + $chatsD + $chatsR;
    echo "user $id ,has $result notifications\n";
    return $result;
}


function getNotifyCounters($id): array
{
    list($resultPending, $error) = getPendingNotifications($id);
    if ($error !== "")
        return [$error, 0, 0];
    $notificationCounter = count($resultPending);
    list($resultUnDeliveredChat, $error) = getUnDeliveredChat($id);
    if ($error !== "")
        return [$error, 0, 0];
    $chatsD = count($resultUnDeliveredChat);
    list($resultUnReadChat, $error) = getUnReadChat($id);
    if ($error !== "")
        return [$error, 0, 0];
    $chatsR = count($resultUnReadChat);
    $chatCounter = $chatsD + $chatsR;
    echo "user $id ,has $chatCounter notifications";
    return array("", $notificationCounter, $chatCounter);
}

function deleteAccountManager($id): bool
{
    $loggedOut = logout($id);
    $deleted = false;
    if ($loggedOut) {
        list($deleted, $error) = deleteUserById($id);
        if ($error !== "") {
            setSessionError($error);
            return false;
        }
    }
    return $deleted;
}

function validateVerificationToken($inputToken): bool
{
    list($user, $error) = getUserByVerificationToken($inputToken, true);
    if ($error !== "") {
        setSessionError($error);
    }
    if (is_array($user)) {
        list($enabled, $error) = enableUser($user['UsersID']);
        if ($error !== "") {
            setSessionError($error);
            return false;
        }
        return $enabled;
    }
    return false;
}

function createUser($username, $email, $password): array
{
    list($token, $error) = createUsers($username, $email, $password);
    if ($error != "")
        return array($token, $error);
    list($user, $error) = getUserByVerificationToken($token);
    if ($error != "")
        return array(false, $error);
    $id = $user['UsersID'];
    list($id, $error) = createDefaultProfileImage($id);
    if ($error != "")
        return array($token, $error);
    list($id, $error) = verify_email($email, $token);

    if ($error != "")
        return array($token, $error);
    return array($token, "");
}

function generate_verification_link($email): array
{
    list($user, $error) = getUserByEmail($email);
    if ($error != "")
        return array(false, $error);
    list($success, $error) = reset_verification_token($email);
    if ($error != "")
        return array(false, $error);
    list($id, $error) = verify_email($email, $user['usersVerificationToken']);
    if ($error != "")
        return array(false, $error);
    return [true, ""];
}

function reset_email($userEmail): array
{
    list($data, $error) = getPasswordReset($userEmail);
    if ($error != "")
        return [false, $error];

    $webAddress = $GLOBALS['rootUrl'];
    $selector = $data['Selector'];
    $token = $data['Token'];
    $url = $webAddress . "/forgot-password-reset.php?selector=" . $selector . "&validator=" . bin2hex($token);
    $subject = "EasyTalk: Forgot password?";
    $body = "<p>EasyTalk received a forgotten password reset request. The reset password link is below. If you did not make this request, you could ignore this email.\n
    </p><p>Here is your reset password link: <br><a href='" . $url . "'>" . $url . "</a></p>";

    $from = 'your-email@gmail.com';
    $fromName = 'Easytalk';
    send_email($userEmail, $from, $fromName, $subject, $body);
    $success_message = 'Reset Password Email sent to ' . $userEmail . ', so before login first reset your password in the received email';
    $_SESSION['message'] = $success_message;
    return [true, ""];
}

function verify_email($userEmail, $code): array
{
    $webAddress = $GLOBALS['rootUrl'];
    $from = 'your-email@gmail.com';
    $fromName = 'Easytalk';
    $subject = 'Registration Verification for EasyTalk Chat Application ';

    $body = '
            <p>Thank you for registering for EasyTalk Chat Application.</p>
                <p>This is a verification email, please click the link to verify your email address.</p>
                <p><a href="' . $webAddress . '/verify.php?code=' . $code . '">Click to Verify</a></p>
            ';

    send_email($userEmail, $from, $fromName, $subject, $body);
    $success_message = 'Verification Email sent to ' . $userEmail . ', so before login first verify your email';
    $_SESSION['message'] = $success_message;
    return [true, ""];
}

function encrypt($plaintext, $password = null, $encoding = 'hex'): bool|string
{
    $password = "your_encryption_key_here";
    if ($plaintext != null && $password != null) {
        $keysalt = openssl_random_pseudo_bytes(16);
        $key = hash_pbkdf2("sha512", $password, $keysalt, 20000, 32, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length("aes-256-gcm"));
        $tag = "";
        $encryptedstring = openssl_encrypt($plaintext, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag, "", 16);
        return $encoding == "hex" ? bin2hex($keysalt . $iv . $encryptedstring . $tag) : ($encoding == "base64" ? base64_encode($keysalt . $iv . $encryptedstring . $tag) : $keysalt . $iv . $encryptedstring . $tag);
    }
    return false;
}

function decrypt($encryptedstring, $password = null, $encoding = 'hex'): bool|string
{
    $password = "your_encryption_key_here";
    if ($encryptedstring != null && $password != null) {
        $encryptedstring = $encoding == "hex" ? hex2bin($encryptedstring) : ($encoding == "base64" ? base64_decode($encryptedstring) : $encryptedstring);
        $keysalt = substr($encryptedstring, 0, 16);
        $key = hash_pbkdf2("sha512", $password, $keysalt, 20000, 32, true);
        $ivlength = openssl_cipher_iv_length("aes-256-gcm");
        $iv = substr($encryptedstring, 16, $ivlength);
        $tag = substr($encryptedstring, -16);
        return openssl_decrypt(substr($encryptedstring, 16 + $ivlength, -16), "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag);
    }
    return false;
}

function send_email($userEmail, $from, $fromName, $subject, $body): array
{
    if (USE_MAIL == 'Native')
        return sendMailViaDefaultMail($userEmail, $subject, $body);
    else
        return sendMailWithPhpMailer($from, $fromName, $userEmail, $subject, $body);
}

/**
 * @param $from
 * @param $fromName
 * @param $userEmail
 * @param $subject
 * @param $body
 * @return array
 * @throws Exception
 */
function sendMailWithPhpMailer($from, $fromName, $userEmail, $subject, $body): array
{
    require __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->SMTPDebug = 0;

    $mail->Host = 'smtp.gmail.com'; // Your SMTP server

    $mail->SMTPAuth = true;

    $mail->Username = 'your-email@gmail.com'; // Your email
    $mail->Password = 'your-app-password';  // Your email password/app password

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = 587;

    // Bypass certificate verification (for XAMPP)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->setFrom($from, $fromName);

    $mail->addAddress($userEmail);

    $mail->isHTML(true);

    $mail->Subject = $subject;
    $mail->Body = $body;

    $mail->send();

    return [true, ""];
}

function sendMailViaDefaultMail($to, $subject, $body): array
{
    $headers = "From: EasyTalk <your-email@gmail.com>\r\n";
    $headers .= "Reply-to: your-email@gmail.com\r\n";
    $headers .= "Content-type: text/html\r\n";
    mail($to, $subject, $body, $headers);
    return [true, ""];
}

/**
 * @param mixed $error
 * @param string $urlTo
 * @param string $buttonName
 * @return void
 */
function checkError(mixed $error, string $urlTo = "friends.php", string $buttonName = "Back to Friends"): void
{
    if ($error != "") {
        echo "<h6 class='alert-danger'><p class='text-center'>$error</p></h6>";
        echo <<< EOD
             <div class="form-group mb-0 d-flex justify-content-between">  
                    <br><p class="muted pt-2">Back to <a href="$urlTo" class="theme-secondary-text">$buttonName<a><p>    </div>;
            EOD;
        exit();
    }
}

/**
 * @return void
 */
function checkAndPrintErrorAtBottom(): void
{
    if (isset($_SESSION["message"])) {
        echo "<p class='alert-success'>{$_SESSION["message"]}</p>";
        unset($_SESSION["message"]);
    } else if (isset($_SESSION["error"])) {
        echo "<p class='alert-danger'>{$_SESSION["error"]}</p>";
        unset($_SESSION["error"]);
    }
}

// Check if a user can receive messages from non-friends
function canReceiveMessagesFromStrangers($userId): bool
{
    $settings = getUserSettings($userId);
    return isset($settings['accept_messages_from_strangers']) &&
        $settings['accept_messages_from_strangers'] == 1;
}

// Check if a user should be shown in search results
function shouldShowInSearch($user, $search): bool
{
    // Check if this is an exact match
    $isExactMatch = (strtolower($user['usersUsername']) === strtolower($search));

    $settings = getUserSettings($user['UsersID']);;
    $hideFromSearch = isset($settings['hide_account_from_search']) &&
        $settings['hide_account_from_search'] == 1;

    // If account is hidden and the search is not exact, don't show the user
    if ($hideFromSearch && !$isExactMatch) {
        return false;
    }

    return true;
}

// Check if email notifications should be sent
function shouldSendEmailNotification($userId): bool
{
    $settings = getUserSettings($userId);
    return isset($settings['email_notifications']) &&
        $settings['email_notifications'] == 1;
}

// Function to get user settings
function getUserSettings($userId): array
{
    $conn = $GLOBALS['_mc'];

    $stmt = $conn->prepare("SELECT setting_name, setting_value FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }

    return $settings;
}

// Function to save user settings
function saveUserSettings($userId, $settings) {
    global $conn;

    // First delete existing settings
    $stmt = $conn->prepare("DELETE FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    // Then insert new settings
    $stmt = $conn->prepare("INSERT INTO user_settings (user_id, setting_name, setting_value) VALUES (?, ?, ?)");

    foreach ($settings as $name => $value) {
        $stmt->bind_param("isi", $userId, $name, $value);
        $stmt->execute();
    }

    return true;
}

// Function to send message notification email
function sendMessageNotificationEmail($userId, $senderName, $messageContent)
{
    list($user, $error) = getUserById($userId);
    if ($error !== "" || !shouldSendEmailNotification($userId)) {
        return false;
    }

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
                <a href='" . $GLOBALS['rootUrl'] . "messages.php' 
                   style='background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                     Reply on EasyTalk
                </a>
            </div>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='color: #666; font-size: 12px; text-align: center;'>
                You received this email because you have email notifications enabled in your EasyTalk settings.<br>
                <a href='" . $GLOBALS['rootUrl'] . "settings.php' style='color: #007bff;'>Manage your notification preferences</a>
            </p>
        </div>
    </body>
    </html>";

    $from = 'your-email@gmail.com';
    $fromName = 'EasyTalk';

    list($success, $error) = send_email($user['usersEmail'], $from, $fromName, $subject, $body);
    return $success;
}

// Function to send friend request notification email
function sendFriendRequestNotificationEmail($userId, $senderName)
{
    list($user, $error) = getUserById($userId);
    if ($error !== "" || !shouldSendEmailNotification($userId)) {
        return false;
    }

    $subject = "New friend request from $senderName - EasyTalk";
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #333;'>You have a new friend request on EasyTalk!</h2>
            <p><strong>" . htmlspecialchars($senderName) . "</strong> wants to be your friend.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . $GLOBALS['rootUrl'] . "notifications.php' 
                   style='background-color: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>
                    Manage Friends
                </a>
            </div>
        </div>
    </body>
    </html>";

    $from = 'your-email@gmail.com';
    $fromName = 'EasyTalk';

    list($success, $error) = send_email($user['usersEmail'], $from, $fromName, $subject, $body);
    return $success;
}