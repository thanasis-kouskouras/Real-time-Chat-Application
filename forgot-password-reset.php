<?php
require_once 'config.php';
$path = $GLOBALS['rootUrl'] . '/static';
require_once "myfavicon.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src='<?php echo $path . "/js/passwordUtilities.js" ?>'></script>
    <title>Reset Password</title>
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
            <div class="col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div class="card-body">
                        <div class="text-center m-auto">
                            <h3 class="text-uppercase text-center">EASYTALK</h3>
                            <h4 id="salmon" class="text-center">Reset Password</h4>
                        </div>
                        <?php
                    $selector = filter_var($_GET["selector"], FILTER_SANITIZE_STRING);
                    $validator = filter_var($_GET["validator"], FILTER_SANITIZE_STRING);
                    if (empty($selector) !== false || empty($validator) !== false) {
                        header("location: login.php?error=invalidatedRqst");
                    } else {
                        if (ctype_xdigit($selector) !== false && ctype_xdigit($validator) !== false) {
                            ?>
                        <form action="includes/forgot-password-reset.inc.php" method="post">
                            <input type="hidden" name="selector" value="<?php echo $selector; ?>">
                            <input type="hidden" name="validator" value="<?php echo $validator; ?>">
                            <div class="form-group mb-3">
                                <label for="password">Password :</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="password" id="password"
                                        placeholder="Enter New Password" required="" minlength="8">
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
                                            name="confirm-password" placeholder="Confirm New Password" required=""
                                            minlength="8">
                                        <div class="input-group-addon">
                                            <span id="view-password" onclick="viewPassword('confirm-password', 'eye2')">
                                                <i class="fa fa-lg fa-eye" aria-hidden="true" id="eye2"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-0 text-center">
                                    <button class="btn btn-primary btn-block" type="submit"
                                        name="forgot-password-reset-submit"> Reset
                                    </button>
                                    <p></p>
                                    <span class="muted pt-2">Back to <a href="login.php"
                                            class="theme-secondary-text">Login</a> </span>
                                </div>
                        </form>
                        <?php
                        }
                    }
                    ?>
                        <div class="text-center m-auto">
                            <p></p>
                            <?php
                        if (!isset($_GET["error"])) {
                            exit();
                        } else {
                            if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptyinput") {
                                echo "<p class='alert-danger'>Fill in all the fields!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidPwd") {
                                echo "<p class='alert-danger'>Invalid Password!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "passwordsdontmatch") {
                                echo "<p class='alert-danger'>Passwords do not match!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "stmtfailed") {
                                echo "<p class='alert-danger'>Something went wrong.Try again!</p>";
                                exit();
                            } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "linkExpired") {
                                echo "<p class='alert-danger'>Your link Expired. Please try to reset again!</p>";
                                exit();
                            }
                        }
                        require 'footerMessage.php';
                        ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require 'footer.php'; ?>