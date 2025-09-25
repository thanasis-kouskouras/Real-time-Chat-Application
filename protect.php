<?php
require_once "includes/functions.inc.php";
// VERIFY JWT
list($user, $error) = is_user_logged_in();
checkError($error);
// JWT COOKIE NOT SET!
if ($user === false || $user === null || isset($_POST["logout"])) {
    setcookie("jwt", null, -1);
    setcookie("remember_me", null, -1);
    
    // Store the current page URL for redirect after login
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get the current page with query parameters
    $current_url = $_SERVER['REQUEST_URI'];
    
    // Don't redirect back to login/logout pages or form handlers
    $excluded_pages = ['login.php', 'logout.php', 'signup.php', 'verify.php'];
    $current_page = basename(parse_url($current_url, PHP_URL_PATH));
    
    if (!in_array($current_page, $excluded_pages)) {
        // Extract just the filename and query params, not the full path
        $path_parts = parse_url($current_url);
        $page_with_query = basename($path_parts['path']);
        if (!empty($path_parts['query'])) {
            $page_with_query .= '?' . $path_parts['query'];
        }
        $_SESSION['redirect_after_login'] = $page_with_query;
    }
    
    header("Location: login.php");
    exit();
} else {
//  JWT COOKIE IS SET!
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}