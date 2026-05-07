<?php
//HTTP Security Headers (applies to all authenticated pages)
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

require __DIR__ . '/vendor/autoload.php';
require 'protect.php';
require_once 'config.php';
require_once 'includes/functions.inc.php';

$user_guid = $user["user_guid"];
$user_guidString = $user_guid;

$path = $GLOBALS['rootUrl'] . '/static';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>EasyTalk</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">

    <!-- Vendor libraries -->
    <script src='<?php echo $path . "/js/vendor/jquery.min.js" ?>'></script>
    <script src='<?php echo $path . "/js/vendor/bootstrap.bundle.js" ?>'></script>

    <!-- Global user data and server configuration -->
    <script>
    window.CURRENT_USER_GUID = <?php echo json_encode($user_guidString); ?>;
    window.WS_CONFIG = <?php echo json_encode(['host' => WS_HOST, 'port' => WS_SERVER_PORT]); ?>;
    window.APP_PATH = <?php echo json_encode(APP_PATH); ?>;
    </script>

    <!-- Loading indicator -->
    <script src='<?php echo $path . "/js/modules/loading-indicator.js" ?>'></script>

    <!-- Toast notifications -->
    <script type="module" src='<?php echo $path . "/js/modules/toast-notifications.js" ?>'></script>

    <!-- API Configuration -->
    <script src='<?php echo $path . "/js/config/apiConfig.js" ?>'></script>

    <!-- API Client and Form Handler -->
    <script src='<?php echo $path . "/js/form-handler.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-utilities.js" ?>'></script>

    <!-- Main application (modular) -->
    <script type="module" src='<?php echo $path . "/js/main.js" ?>'></script>

    <!-- Header scripts (navbar, badges, etc.) -->
    <script src='<?php echo $path . "/js/header-scripts.js" ?>'></script>

    <!-- Logout handler -->
    <script src='<?php echo $path . "/js/logout.js" ?>'></script>

    <!-- Chat managers -->
    <script src='<?php echo $path . "/js/chat/chat-manager.js" ?>'></script>
    <script src='<?php echo $path . "/js/chat/group-chat-manager.js" ?>'></script>
    <script src='<?php echo $path . "/js/chat/message-loader.js" ?>'></script>

    <link rel="shortcut icon" type="image/png" href="img/logo.png" />
    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/buttons.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/mobile.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/loading-indicator.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/toast-notifications.css" ?>' />
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top bg-light">
            <input id="token" value="<?php echo htmlspecialchars($user_guidString, ENT_QUOTES, 'UTF-8'); ?>" hidden>
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <div class="d-flex align-items-center">
                                <img id='headerProfileImage' src='img/profiledefault.jpg' alt='Profile Image'>
                                <strong><?php echo htmlspecialchars($user['user_username']); ?></strong>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a id="blue" class="nav-link active" aria-current="page" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profile</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="friends.php">Friends</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="groups.php">Groups</a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="messages.php"><span class="nav-text">Messages</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notifications.php"><span class="nav-text">Notifications</span></a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="about_us.php">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="contact_us.php">Contact Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">Settings</a>
                        </li>
                        <?php if (isset($user['user_role']) && $user['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="user-management.php">User Management</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a id="salmon" class="nav-link active" href="#" data-logout-link>Logout</a>
                        </li>
                    </ul>
                </div>
                <form class="d-flex" action="search.php" method="get">
                    <input class="form-control me-2" id="search" type="search" name="search"
                        placeholder="Search users..." required="" aria-label="Search" maxlength="30" <?php
                       //Check both POST and GET for search value
                       $searchValue = '';
                       if (isset($_POST["search"])){
                           $searchValue = htmlspecialchars($_POST["search"]);
                       } else if (isset($_GET["search"])){
                           $searchValue = htmlspecialchars($_GET["search"]);
                       }
                       if ($searchValue !== ''){
                           if (!invalidUsername($searchValue)){
                               echo 'value="' . $searchValue . '"';
                           }
                       }
                       ?>>
                    <button class="app-btn app-btn-outline-primary btn-min-w-100" type="submit" name="search-submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </nav>
    </header>