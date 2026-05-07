<?php
/* AUTHENTICATION FUNCTIONS

Core authentication, session management, and JWT functions. */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db/users.php';
require_once __DIR__ . '/remember-me.php';

function emailExists($email): bool
{
    list($data, $error) = getUserByEmail($email);
    if ($error !== "" || !is_array($data)) {
        return false;
    }
    return true;
}

function usernameExists($username): bool
{
    $conn = getDbConnection();
    $sql = "SELECT 1 FROM users WHERE user_username = ? AND user_is_deleted != 1 LIMIT 1";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function isPasswordCorrectByGuid($password, $user_guid)
{
    list($user, $error) = getUserByGuid($user_guid);
    if ($error !== "") {
        setSessionError($error);
        return false;
    }
    $passwordHashed = $user["user_password"];

    return password_verify($password, $passwordHashed);
}

function isVerified($email): bool
{
    list($user, $error) = getUserByEmail($email);
    if ($error !== "") {
        setSessionError($error);
        return false;
    }
    if (is_array($user)) {
        if ($user['user_email_verified'] == 'True')
            return true;
    }
    return false;
}

function validate($jwt): bool|array
{
    require_once __DIR__ . "/../vendor/autoload.php";

    //Skip invalid token values
    if (empty($jwt) || $jwt === 'deleted' || !is_string($jwt) || substr_count($jwt, '.') !== 2) {
        return false;
    }

    try {
        $decoded = Firebase\JWT\JWT::decode($jwt, new Firebase\JWT\Key(JWT_SECRET, JWT_ALGO));
        //Validate issuer and audience claims (rejects tokens not issued by this application)
        if (($decoded->iss ?? '') !== JWT_ISSUER || ($decoded->aud ?? '') !== JWT_AUD) {
            return false;
        }
        $jwt = $decoded;
        $valid = is_object($jwt);
    } catch (Firebase\JWT\ExpiredException|Firebase\JWT\SignatureInvalidException) {
        setSessionError("Your Session has expired. Please login again.");
        return false;
    } catch (UnexpectedValueException|Exception) {
        //Invalid JWT format
        return false;
    }

    //Get User (use GUID from JWT)
    if ($valid) {
        //Check if JWT contains GUID
        if (isset($jwt->data->guid)) {
            list($user, $error) = getUserByGuid($jwt->data->guid);
        } else {
            setSessionError("Legacy JWT format no longer supported. Please log in again.");
            return false;
        }
        
        if ($error !== "") {
            setSessionError($error);
            return false;
        }
        $valid = is_array($user);
    }

    //Return Result
    if ($valid) {
        unset($user["user_password"]);
        return $user;
    } else {
        return false;
    }
}

function generate_jwt($user_guid): string
{
    require_once __DIR__ . "/../vendor/autoload.php";
    $now = strtotime("now");
    try {
        return Firebase\JWT\JWT::encode([
            "iat" => $now, //issued at (time when token is generated)
            "nbf" => $now, //not before (when this token is considered valid)
            "exp" => $now + JWT_TIME, //expiry
            "jti" => base64_encode(random_bytes(16)), //json token id
            "iss" => JWT_ISSUER, //issuer
            "aud" => JWT_AUD, //audience
            "data" => ["guid" => $user_guid]
        ], JWT_SECRET, JWT_ALGO);
    } catch (Exception $e) {
        app_log($e->getMessage());
        setSessionError("An error has Occurred. Please try again.");
        return "";
    }
}

function init_session($user): array|bool
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    session_regenerate_id(true); //Regenerate session ID on login to prevent session fixation attacks

    $_SESSION["UserGuid"] = $user["user_guid"];
    $_SESSION["userUid"] = $user["user_username"];
    $_SESSION["userEmail"] = $user["user_email"];
    $_SESSION["user_role"] = $user["user_role"] ?? 'user';
    
    $jwt = generate_jwt($user['user_guid']);
    $jwtCookieTime = time() + JWT_TIME;
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    if ($jwt) {
        setcookie("jwt", $jwt, [
            'expires'  => $jwtCookieTime,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else
        return false;

    unset($user["user_password"]);
    $user['jwtTime'] = $jwtCookieTime;
    $user['jwt'] = $jwt;
    return $user;
}

function core_login($email, $password, $remember = false): array
{
    $emailExists = emailExists($email);
    if (!$emailExists) //Email not found
        return [false, "Invalid email or password.", 401];

    $isVerified = isVerified($email);
    if (!$isVerified) //User not verified
        return [false, "<div class='auth-msg-body'><strong>Email not verified!</strong><br>Please check your inbox for the email verification link.<br><br><div class='auth-msg-btn-row'><button type='button' onclick=\"regenerate_verify_email('$email')\" class='btn btn-success auth-resend-btn'>Click here to resend verification link</button></div></div>", 403];

    list($user, $error) = getUserByEmail($email);
    if ($error !== "") { //User not found in db
        return [$user, $error, 500];
    }

    //Check if user is banned
    if (is_array($user) && isset($user['user_banned']) && $user['user_banned'] == 1) {
        return [false, "Your account has been banned.", 403];
    }

    $valid = is_array($user);
    if ($valid) {
        $valid = password_verify($password, $user['user_password']);
    }
    
    //If password is valid, initialize session and return user
    if ($valid) {
        if ($remember) {
            list($token, $rememberTime) = rememberMeByGuid($user['user_guid']);
            $user['rememberToken'] = $token;
            $user['rememberTokenTime'] = $rememberTime;
        }
        return [init_session($user), "", 200];
    } else {
        return [false, "Invalid email or password.", 401];
    }
}

function is_user_logged_in(): bool|array
{
    //1. Check the jwt
    if (isset($_COOKIE['jwt'])) {
        $user = validate($_COOKIE["jwt"]);
        if ($user !== false)
            return [$user, ""];
    }
    //2. Check the remember_me in cookie
    $token = htmlspecialchars((string) filter_input(INPUT_COOKIE, 'remember_me'));
    if ($token && tokenIsValid($token)) {
        list($user, $error) = getUserByToken($token);
        if ($error !== "") {
            $_SESSION['error'] = $error; //Add the error to session
            return [false, ""];
        }
        if (is_array($user))
            return [init_session($user), ""];
    }
    /* 3. Not logged in
    Start session even if no user logged in , because we give him errors from session array. */
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return [false, ""];
}

function is_user_logged_in_websocket(string|null $jwt, string|null $remember_me): bool|array
{
    //1. Check the jwt
    if ($jwt != null) {
        $user = validate($jwt);
        if ($user !== false)
            return [$user, ""];
    }
    //2. Check the remember_me in cookie
    if ($remember_me != null) {

        $token = htmlspecialchars((string)$remember_me);

        if ($token && tokenIsValid($token)) {
            list($user, $error) = getUserByToken($token);
            if ($error !== "") {
                return [false, ""];
            }
            if (is_array($user)) {
                unset($user["user_password"]);
                return [$user, ""];
            }
        }
    }
    //3. Not logged in
    return [false, ""];
}

function logout($user_guid): bool
{
    require_once __DIR__ . '/db/usertokens.php';
    
    //Delete the user token
    list($result, $error) = deleteUserTokenByGuid($user_guid);
    if ($error !== "") {
        setSessionError($error);
        return false;
    }

    //Delete remember_me
    $isSecureClear = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    if (isset($_COOKIE['remember_me'])) {
        unset($_COOKIE['remember_me']);
        setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $isSecureClear, 'httponly' => true, 'samesite' => 'Lax']);
    }

    //Delete jwt
    if (isset($_COOKIE['jwt'])) {
        unset($_COOKIE['jwt']);
        setcookie('jwt', '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $isSecureClear, 'httponly' => true, 'samesite' => 'Lax']);
    }

    //Clear session
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_unset();
        session_destroy();
    }

    return true;
}

function setSessionError($message): void
{
    $_SESSION['error'] = $message; //Add the error to session
}