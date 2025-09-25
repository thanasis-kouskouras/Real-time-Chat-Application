<?php

// PROCESS LOGIN
require "includes/functions.inc.php";
list($user, $error) = is_user_logged_in();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Check if redirect parameter is provided in URL (from email links)
if (!empty($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

//session_start();
if ($error !== "")
    setSessionError($error);
//if (is_array($user))
if ($user === false) {
//  NOT LOGGED IN
    setcookie("jwt", null, -1, "/");
} else {
//  ALREADY SIGNED IN
    header("Location: index.php");
    exit();
}
$path = $GLOBALS['rootUrl'] . '/static';
require_once "myfavicon.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src='<?php echo $path . "/js/jquery.min.js" ?>'></script>
    <script src='<?php echo $path . "/js/passwordUtilities.js" ?>'></script>
    <script src='<?php echo $path . "/js/login.js" ?>'></script>
    <title>EasyTalk-Login</title>

    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <script src='<?php echo $path . "/js/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_js_bootstrap.bundle.js" ?>'>
    </script>
    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />

    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/forgot-password.css" ?>' />

</head>

<body class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <img class="logo-login" src="img/logo.png" alt="Logo">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div class="card-body">
                        <div class="text-center m-auto">
                            <h2 class="text-uppercase text-center">LOGIN</h2>
                        </div>
                        <form action="includes/login.inc.php" method="post">
                            <div class="form-group mb-3">
                                <label for="email">Email :</label>
                                <?php
                            if (isset($_GET["email"])) {
                                $email = filter_var($_GET["email"], FILTER_SANITIZE_EMAIL);
                                echo '<input type="email" name="email" placeholder="Enter Email Address" class="form-control" value="' . $email . '">';
                            } else {
                                echo '<input type="email" name="email" placeholder="Enter Email Address" class="form-control" required="">';
                            }
                            ?>
                            </div>
                            <div class="form-group mb-3">
                                <label for="password">Password :</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="password" id="password"
                                        placeholder="Enter Password" required="">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('password')"><i
                                                class="fa fa-lg fa-eye" aria-hidden="true" id="eye"></i></span>
                                    </div>
                                </div>
                                <div class="form-group mb-3">
                                    <div class="custom-control custom-checkbox checkbox-success">
                                        <label for="remember_me">
                                            <input type="checkbox" name="remember_me" id="remember_me" value="checked"
                                                <?= $remember_me ?? '' ?> />
                                            Keep me logged in</label>
                                    </div>
                                </div>
                                <div class="form-group mb-0 text-center">
                                    <button class="btn btn-primary btn-block" type="submit" name="login-submit"> Log In
                                    </button>
                                </div>
                        </form>
                        <p></p>
                        <div class="form-group mb-0 d-flex justify-content-between">
                            <div id="left">
                                <a href="forgot-password.php" class="link-secondary" id="salmon">Forgot Password?</a>
                            </div>
                            <div id="center">
                                <a href="signup.php" class="theme-secondary-text">Sign Up →</a>
                            </div>
                        </div>

                        <div class="text-center m-auto">
                            <p></p>
                            <?php
                        require 'footerMessage.php';
                        checkAndPrintErrorAtBottom();
                        if (isset($_GET["error"])) {
                            if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptyInputLogin") {
                                echo "<p class='alert-danger'>Fill in all the fields!</p>";
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "wrongEmail") {
                                echo "<p class='alert-danger'>Invalid Email address!</p>";
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "wrongPassword") {
                                echo "<p class='alert-danger'>Invalid Password!</p>";
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "stmtfailed") {
                                echo "<p class='alert-danger'>Something went wrong.\nInitiate the reset password process again!</p>";
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "unknown") {
                                echo "<p class='alert-danger'>Something went wrong!</p>";
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidatedRqst") {
                                echo "<p class='alert-danger'>Request can not be validated.Try again!</p>";
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "PasswordResetOk") {
                                echo "<p class='alert-success'>Password has been reset successfully!</p>";
                            }
                        }
                        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>