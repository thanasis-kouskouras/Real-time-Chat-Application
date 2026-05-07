<?php
/* PAGE PROTECTION (REQUIRE AUTHENTICATION)
 
This file is included at the top of any page that requires authentication.
Uses the unified authentication system. */

require_once __DIR__ . '/includes/auth.php';

$user = requireAuthentication(); //Redirects to login if not authenticated

//Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}