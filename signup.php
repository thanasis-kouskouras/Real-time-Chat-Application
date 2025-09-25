<?php
ini_set('display_errors', 0); // disable error display
ini_set('log_errors', 0); // disable error logging

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/functions.inc.php";
$path = $GLOBALS['rootUrl'] . '/static';
require_once "myfavicon.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src='<?php echo $path . "/js/passwordUtilities.js" ?>'></script>
    <title>EasyTalk-SignUp</title>
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
            <img class="logo-signup" src="img/logo.png" alt="Logo">
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div class="card-body">


                        <div class="text-center m-auto">
                            <h2 class="text-uppercase text-center">Sign Up</h2>
                        </div>


                        <form action="includes/signup.inc.php" method="post">
                            <input type="hidden" name="csrftoken" value="your_csrf_token_here">
                            <div class="form-group mb-3">
                                <label for="username">Username :</label>
                                <?php
                            if (isset($_GET["username"])) {
                                $username = filter_var($_GET["username"], FILTER_SANITIZE_STRING);
                                echo '<input type="text" name="username" placeholder="Enter Username" class="form-control" value="' . $username . '">';
                            } else {
                                echo '<input id="username" type="text" name="username" minlength="1"
                                        maxlength="20" placeholder="Enter Username" class="form-control" required="">';
                                echo '<span class="red" id="span_error_username"></span>';
                            }
                            ?>
                            </div>
                            <div class="form-group mb-3">
                                <label for="emailaddress">Email :</label>
                                <?php
                            if (isset($_GET["email"])) {
                                $email = filter_var($_GET["email"], FILTER_SANITIZE_EMAIL);
                                echo '<input type="email" name="email" placeholder="Enter Email Address" class="form-control" value="' . $email . '">';
                            } else {
                                echo '<input id="email" type="email" name="email" placeholder="Enter Email Address" class="form-control" required="">';
                                echo '<span class="red" id="span_error_email"></span>';
                            }
                            ?>
                            </div>
                            <div class="form-group mb-3">
                                <label for="password">Password :</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="password" id="password"
                                        placeholder="Enter Password" minlength="8" required="">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('password','eye')"><i
                                                class="fa fa-lg fa-eye" aria-hidden="true" id="eye"></i></span>
                                        <span class="red" id="span_error_password"></span>
                                    </div>
                                </div>
                                <p></p>
                                <div class="form-group mb-3">
                                    <label for="confirm-password">Confirm Password :</label>
                                    <div class="input-group bg-light">
                                        <input type="password" class="form-control" id="confirm-password" value=""
                                            name="confirm-password" placeholder="Confirm Password" minlength="8"
                                            required="">
                                        <div class="input-group-addon">
                                            <span id="view-password"
                                                onclick="viewPassword('confirm-password','eye2')"><i
                                                    class="fa fa-lg fa-eye" aria-hidden="true" id="eye2"></i></span>
                                            <span class="red" id="span_error_password"></span>
                                        </div>
                                    </div>
                                    <span class="red" id="span_error_confirm-password"></span>
                                </div>

                                <div id="signup-form" class="form-group mb-0 d-flex justify-content-between">
                                    <div id="left">
                                        <button id="submit_signup" class="btn btn-primary btn-block" type="submit"
                                            name="signup-submit">Sign Up
                                        </button>
                                    </div>
                                    <div id="center">
                                        <p class="muted pt-2">Back to <a href="login.php"
                                                class="theme-secondary-text">Login
                                                →</a></p>
                                    </div>
                                </div>
                        </form>
                        <div class="text-center m-auto">
                            <p></p>
                            <?php
                        require 'footerMessage.php';
                        checkAndPrintErrorAtBottom();
                        if (!isset($_GET["error"])) {
                            exit();
                        } else {
                            if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "vermail") {
                                echo "<p class='alert-success'>Please check your email to verify your account!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptyinputsignup") {
                                echo "<p class='alert-danger'>Fill in all the fields!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidusername") {
                                echo "<p class='alert-danger'>Invalid Username. Only letters, numbers and _- of the special characters are allowed!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidemail") {
                                echo "<p class='alert-danger'>Invalid Email address!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidPassword") {
                                echo "<p class='alert-danger'>Invalid Password!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "passwordsdontmatch") {
                                echo "<p class='alert-danger'>Passwords do not match!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "stmtfailed") {
                                echo "<p class='alert-danger'>Something went wrong.Try again!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emailtaken") {
                                echo "<p class='alert-danger'>This Email address already exists.Enter an other valid Email!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "none") {
                                echo "<p class='alert-success'>Successfully signed up!Please verify your account by clicking the link of the Easytalk email that arrived in your email inbox</p>";
                                exit();
                            }
                        }
                        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.0-beta1/js/bootstrap.bundle.min.js">
    </script>

    <!-- Option 2: Separate Popper and Bootstrap JS -->

    <!-- https://cdnjs.com/libraries/popper.js/2.5.4 -->
    <!-- <script
  src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.5.4/umd/popper.min.js"
></script>
<script
  src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.0.0-beta1/js/bootstrap.min.js"
></script> -->

    <!-- More: https://getbootstrap.com/docs/5.0/getting-started/introduction/ -->
</body>

</html>