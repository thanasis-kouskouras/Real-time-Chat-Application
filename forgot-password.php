<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

//Generate CSRF token per session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$path = $GLOBALS['rootUrl'] . '/static';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="img/logo.png" />
    <title>EasyTalk - Forgot Password?</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">

    <script src='<?php echo $path . "/js/vendor/bootstrap.bundle.js" ?>'></script>
    <script src='<?php echo $path . "/js/modules/loading-indicator.js" ?>'></script>
    <script src='<?php echo $path . "/js/config/apiConfig.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-handler.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-utilities.js" ?>'></script>
    <script src='<?php echo $path . "/js/auth-page.js" ?>'></script>
    <script type="module" src='<?php echo $path . "/js/modules/toast-notifications.js" ?>'></script>

    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/buttons.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/loading-indicator.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/toast-notifications.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/mobile.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/forgot-password.css" ?>' />
</head>

<body class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4 col-xl-4">
                <div class="card p-4">
                    <div class="card-body">
                        <div class="text-center m-auto">
                            <i id="faForgot" class="fa fa-lock"></i>
                        </div>

                        <div class="text-center">
                            <h4 id="salmon">Forgot Password?</h4>
                        </div>
                        <form action="#" method="post" id="forgot-password-form">
                            <input type="hidden" name="csrftoken"
                                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <div class="input-group">
                                    <div id="forgot-password-email" class="input-group-addon bg-light"><i
                                            class="fa fa-envelope-o color-blue"></i></div>
                                    <input id="user_email" name="user_email" placeholder="Enter your email address"
                                        class="form-control" type="email" maxlength="254" required="required">
                                </div>
                            </div>
                            <div class="form-group text-center mt-4 mb-3">
                                <button class="app-btn app-btn-primary btn-min-w-150" type="submit">
                                    <i class="fa-solid fa-refresh"></i>Reset Password
                                </button>
                            </div>
                            <div class="text-center">
                                <p class="muted">Back to <a href="login.php">Login →</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require 'footer.php'; ?>