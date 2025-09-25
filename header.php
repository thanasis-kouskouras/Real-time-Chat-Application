<?php

ini_set('display_errors', 1); // disable error display
ini_set('log_errors', 1); // disable error logging
require __DIR__ . '/vendor/autoload.php';
require 'protect.php';
require_once 'config.php';
require_once 'includes/functions.inc.php';
$userid = $user["UsersID"];
$userUsername = $user["usersUsername"];
$useremail = $user["usersEmail"];
$userGuid = $user["usersGuid"];
$path = $GLOBALS['rootUrl'] . '/static';
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <title>EasyTalk</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src='<?php echo $path . "/js/jquery.min.js" ?>'></script>
    <script src='<?php echo $path . "/js/utilities.js" ?>'></script>
    <script src='<?php echo $path . "/js/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_js_bootstrap.bundle.js" ?>'>
    </script>
    <link rel="shortcut icon" type="image/png" href="img/logo.png" />
    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top bg-light">
            <input id="token" value="<?php echo $userGuid ?>" hidden>
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item">
                            <?php
                        list($rowImg, $error) = getProfileImage($userid);
                        checkError($error);
                        echo "<div>";
                        if ($rowImg['imgStatus'] == 0) {
                            echo "<img id='profileImage' src='uploads/profile" . $userid . "." . $rowImg['imgType'] . "'>";
                        } else {
                            echo "<img id='profileImage' src='img/profiledefault.jpg' >";
                        }
                        echo "<strong > " . $user['usersUsername'] . " </strong>";
                        echo "</div>";
                        ?>
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
                            <a class="nav-link" href="messages.php">Messages <span id="message_header"
                                    class="alert-notifications">
                                    <?php
            list($chat1, $error) = getUnReadChat($userid);
            checkError($error);
            list($chat2, $error) = getUnDeliveredChat($userid);
            checkError($error);
            if (is_array($chat1)) {
                if (is_array($chat2)) {
                    echo count($chat1) + count($chat2);
                } else
                    echo count($chat1);
            }
            ?>
                                </span> </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notifications.php">Notifications <span id="notify_header"
                                    class="alert-notifications">
                                    <?php
            list($notifications, $error) = getPendingNotifications($userid);
            checkError($error);
            if (is_array($notifications)) {
                echo count($notifications);
            }
            ?>
                                </span> </a>
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
                        <li class="nav-item">
                            <a id="salmon" class="nav-link active" href="includes/logout.inc.php">Logout</a>
                        </li>
                    </ul>
                </div>
                <form class="d-flex" action="search.php" method="post">
                    <input class="form-control me-2" id="search" type="search" name="search" placeholder="Search"
                        required="" aria-label="Search" <?php
                       if (isset($_POST["search-submit"])){
                       $search = htmlspecialchars($_POST["search"]);
                       require_once 'includes/functions.inc.php';
                       if (!invalidUsername($search) !== false){
                       ?>value=<?php echo $search;
                }
                } ?>>
                    <button class="btn btn-outline-primary" type="submit" name="search-submit">Search</button>
                </form>
            </div>
        </nav>
    </header>
    <script>
    // Close navbar when clicking outside or on a nav-link
    document.addEventListener('DOMContentLoaded', function() {
        var navbarCollapse = document.getElementById('navbarSupportedContent');
        if (!navbarCollapse) return;

        // Use Bootstrap Collapse API
        var bsCollapse = new bootstrap.Collapse(navbarCollapse, {
            toggle: false
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            var isOpen = navbarCollapse.classList.contains('show');
            if (!isOpen) return;

            var clickedInside = navbarCollapse.contains(e.target) ||
                (document.querySelector('.navbar-toggler') && document.querySelector('.navbar-toggler')
                    .contains(e.target)) ||
                (document.querySelector('.navbar') && document.querySelector('.navbar').contains(e
                    .target) && e.target.closest('.navbar-collapse'));

            // If click is not inside the collapse nor on the toggler, close it
            if (!clickedInside) {
                bsCollapse.hide();
            }
        }, {
            passive: true
        });

        // Close when a nav-link is clicked (use event delegation)
        navbarCollapse.addEventListener('click', function(e) {
            var isLink = e.target.classList && e.target.classList.contains('nav-link');
            var isDropdownItem = e.target.classList && e.target.classList.contains('dropdown-item');
            if ((isLink || isDropdownItem) && navbarCollapse.classList.contains('show')) {
                // Slight delay to allow navigation highlight
                setTimeout(function() {
                    bsCollapse.hide();
                }, 100);
            }
        });

        // Optional: close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && navbarCollapse.classList.contains('show')) {
                bsCollapse.hide();
            }
        });
    });
    </script>