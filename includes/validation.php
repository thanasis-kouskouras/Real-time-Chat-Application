<?php
/* VALIDATION FUNCTIONS

Input validation and sanitization functions. */

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

function usernameTooLong($username, int $maxLength = 30): bool
{
    return mb_strlen($username, 'UTF-8') > $maxLength;
}

function usernameTooShort($username, int $minLength = 1): bool
{
    return mb_strlen($username, 'UTF-8') < $minLength;
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
    $email = sanitizeEmail($email);
    return !validateEmail($email);
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

function emptyInputLogin($email, $password): bool
{
    if (empty($email) || empty($password)) {
        return true;
    }
    return false;
}

//Sanitize string input (replacement for deprecated FILTER_SANITIZE_STRING)
function sanitizeString($input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/* Sanitize integer input (0 if invalid).
Extracts only numeric characters and converts to integer. */
function sanitizeInt($input): int
{
    //Remove all non-numeric characters except minus sign
    $cleaned = preg_replace('/[^0-9-]/', '', (string)$input);
    return (int)$cleaned;
}

function sanitizeEmail($email): string
{
    //Trim whitespace
    $email = trim($email);
    
    //Convert to lowercase for consistency
    $email = strtolower($email);
    
    //Remove any characters that are not valid in email addresses and keep letters, numbers, and valid email special chars: !#$%&'*+-/=?^_`{|}~@.
    $email = preg_replace('/[^a-z0-9!#$%&\'*+\-\/=?^_`{|}~@.\[\]]/', '', $email);
    
    return $email;
}

function validateEmail($email): bool
{
    //Check basic format validation
    if (empty($email) || strlen($email) > 254) {
        return false;
    }
    
    //Use filter_var for validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    //Additional validation check for valid domain structure
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return false;
    }
    
    list($local, $domain) = $parts;
    
    //Local part should not be empty and not exceed 64 characters
    if (empty($local) || strlen($local) > 64) {
        return false;
    }
    
    //Domain should have at least one dot and valid structure
    if (empty($domain) || !str_contains($domain, '.')) {
        return false;
    }
    
    return true;
}