<?php
require_once 'config.php';
$path = $GLOBALS['rootUrl'] . '/static';
require_once "myfavicon.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Forgot Password?</title>
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
                    <div class="card-body"></div>
                    <div class="text-center m-auto">
                        <i id="faForgot" class="fa fa-lock"></i>
                    </div>

                    <div class="text-center">
                        <h4 id="salmon">Forgot Password?</h4>
                    </div>
                    <form action="includes/forgot-password.inc.php" method="post">
                        <div class="form-group">
                            <div class="input-group">
                                <div id="forgot-password-email" class="input-group-addon bg-light"><i
                                        class="fa fa-envelope-o color-blue"></i></div>
                                <input id="email" name="email" placeholder="Enter your Email Address"
                                    class="form-control" type="email" required="required">
                            </div>
                        </div>
                        <div class="form-group text-center mt-4 mb-3">
                            <button class="btn btn-primary" type="submit" name="forgot-password-submit">Reset Password
                            </button>
                        </div>
                        <div class="text-center">
                            <p class="muted">Back to <a href="login.php">Login</a></p>
                        </div>
                    </form>
                    <div class="text-center">
                        <?php
                    if (!isset($_GET["error"])) {
                        exit();
                    } else {
                        if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emptyinput") {
                            echo "<p class='alert-danger'>Fill in the Email address field!</p>";
                            exit();
                        } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "invalidemail") {
                            echo "<p class='alert-danger'>Invalid Email address!</p>";
                            exit();
                        } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "emailnotexist") {
                            echo "<p class='alert-danger'>Email address does not exist!</p>";
                            exit();
                        } else if (filter_var($_GET["error"], FILTER_SANITIZE_STRING) == "stmtfailed") {
                            echo "<p class='alert-danger'>Something went wrong.Try again!</p>";
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
    <?php require 'footer.php'; ?>