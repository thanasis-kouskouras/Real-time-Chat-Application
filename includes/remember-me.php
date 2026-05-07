<?php
/* REMEMBER ME TOKEN MANAGEMENT

Functions for generating and validating remember me tokens. */

require_once __DIR__ . '/db/usertokens.php';

function generateTokens(): array|bool
{
    try {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        app_log($e->getMessage());
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
    //Parse the token to get the selector and validator
    $parsed = parse_token($token);
    if ($parsed === null) {
        return false;
    }
    [$selector, $validator] = $parsed;
    list($tokens, $error) = getUserTokenBySelector($selector);
    if ($error !== "" || !$tokens) {
        return false;
    }
    return password_verify($validator, $tokens['hashed_validator']);
}


function rememberMeByGuid(string $user_guid, int $interval = REMEMBER_ME_INTERVAL): array|bool
{
    [$selector, $validator, $token] = generateTokens();

    //Remove all existing token associated with the user guid
    list($result, $error) = deleteUserTokenByGuid($user_guid);
    if ($error != "" || !$result) {
        return false;
    }
    // Set expiration date
    $expired_seconds = time() + $interval;

    //Insert a token to the database
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);
    list($result, $error) = createUserTokenByGuid($user_guid, $selector, $hash_validator, $expiry);
    if ($error !== "" || !$result) {
        return false;
    }

    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    setcookie('remember_me', $token, [
        'expires'  => $expired_seconds,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    return array($token, $expired_seconds);
}