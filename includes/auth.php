<?php

require_once __DIR__ . '/functions.inc.php';

/* Authenticate user from cookies (for web pages and API endpoints).
It checks JWT first, then remember_me cookie. */
function authenticateUserFromCookies(bool $requireEmailVerified = false, bool $checkBanned = false): array
{
    //1. Check JWT token first
    if (isset($_COOKIE['jwt'])) {
        $user = validate($_COOKIE['jwt']);
        if ($user !== false && is_array($user)) {
            //Additional checks for banned user & verified email
            if ($checkBanned && isset($user['user_banned']) && $user['user_banned'] == 1) {
                return [false, "Your account has been banned."];
            }

            if ($requireEmailVerified && isset($user['user_emailVerified']) && $user['user_emailVerified'] !== 'True') {
                return [false, "Please verify your email address"];
            }

            return [$user, ""];
        }
    }

    //2. Check remember_me token as fallback
    if (isset($_COOKIE['remember_me'])) {
        $token = htmlspecialchars($_COOKIE['remember_me']);
        if ($token && tokenIsValid($token)) {
            list($user, $error) = getUserByToken($token);
            if ($error !== "") {
                return [false, $error];
            }
            
            if (is_array($user)) {
                //Additional checks for banned user & verified email
                if ($checkBanned && isset($user['user_banned']) && $user['user_banned'] == 1) {
                    return [false, "Your account has been banned."];
                }
                
                if ($requireEmailVerified && isset($user['user_emailVerified']) && $user['user_emailVerified'] !== 'True') {
                    return [false, "Please verify your email address"];
                }
                
                return [$user, ""];
            }
        }
    }
    
    return [false, ""]; //3. If not authenticated
}

//Require authentication for web pages (redirects to login if not authenticated) 
function requireAuthentication(bool $requireEmailVerified = false, bool $checkBanned = false): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    list($user, $error) = authenticateUserFromCookies($requireEmailVerified, $checkBanned);
    
    //Check for logout request
    if ($user === false || $user === null || isset($_POST["logout"])) {
        //Clear cookies
        $_isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie("jwt", '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $_isSecure, 'httponly' => true, 'samesite' => 'Lax']);
        setcookie("remember_me", '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $_isSecure, 'httponly' => true, 'samesite' => 'Lax']);
        
        //Store the current page URL for redirect after login
        $current_url = $_SERVER['REQUEST_URI'];
        $excluded_pages = ['login.php', 'logout.php', 'signup.php', 'verify.php']; 
        $current_page = basename(parse_url($current_url, PHP_URL_PATH));
        
        if (!in_array($current_page, $excluded_pages)) {
            //Extract the filename and query params
            $path_parts = parse_url($current_url);
            $page_with_query = basename($path_parts['path']);

            //If basename returns a directory name or empty, default to index.php
            if (empty($page_with_query) || !str_contains($page_with_query, '.')) {
                $page_with_query = 'index.php';
            }

            if (!empty($path_parts['query'])) {
                $page_with_query .= '?' . $path_parts['query'];
            }
            $_SESSION['redirect_after_login'] = $page_with_query;
        }
        
        if ($error !== "") {
            $_SESSION['error'] = $error;
        }

        /* Pass marker indicates user was redirected from a protected page.
        This helps login.php distinguish between fresh redirects and stale session data. */
        header("Location: login.php?from_protected=1");
        exit();
    }
    
    return $user;
}

//Require authentication for API endpoints (returns JSON error if not authenticated)
function requireApiAuthentication(bool $requireEmailVerified = true, bool $checkBanned = true): array
{
    list($user, $error) = authenticateUserFromCookies($requireEmailVerified, $checkBanned);

    if ($user === false || $user === null) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error !== "" ? $error : 'Authentication required. Please log in.'
        ]);
        exit();
    }

    return $user;
}