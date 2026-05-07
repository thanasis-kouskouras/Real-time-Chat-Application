<?php
/* Legacy functions.inc.php - Backward Compatibility File

This file includes all the split modules. */

//Core database dependencies (required by modules)
require_once __DIR__ . '/db/addrequest.php';
require_once __DIR__ . '/db/attachments.php';
require_once __DIR__ . '/db/users.php';
require_once __DIR__ . '/db/chatbox.php';
require_once __DIR__ . '/db/profileImage.php';
require_once __DIR__ . '/db/passwordReset.php';
require_once __DIR__ . '/db/usertokens.php';
require_once __DIR__ . '/../config.php';

//Load split modules (in dependency order)
require_once __DIR__ . '/validation.php';           // No dependencies
require_once __DIR__ . '/encryption.php';           // Depends on: config.php
require_once __DIR__ . '/guid-utilities.php';       // Depends on: dbh.inc.php
require_once __DIR__ . '/remember-me.php';          // Depends on: usertokens
require_once __DIR__ . '/authentication.php';       // Depends on: users, remember-me, utilities
require_once __DIR__ . '/email-service.php';        // Depends on: users, passwordReset
require_once __DIR__ . '/user-service.php';         // Depends on: users, authentication,email-service
require_once __DIR__ . '/file-helpers.php';         // Depends on: attachments
require_once __DIR__ . '/notification-helpers.php'; // Depends on: users, chatbox, profileImage