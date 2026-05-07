<?php
require_once 'config.php';
require_once 'includes/functions.inc.php';

if (session_status() === PHP_SESSION_NONE) {
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
    <title>EasyTalk - Reset Password</title>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">

    <script src='<?php echo $path . "/js/modules/loading-indicator.js" ?>'></script>
    <script src='<?php echo $path . "/js/config/apiConfig.js" ?>'></script>
    <script src='<?php echo $path . "/js/auth.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-handler.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-utilities.js" ?>'></script>
    <script src='<?php echo $path . "/js/auth-page.js" ?>'></script>
    <script type="module" src='<?php echo $path . "/js/modules/toast-notifications.js" ?>'></script>
    <script>
    //Make APP_PATH available to JavaScript for proper redirects
    window.APP_PATH = '<?php echo APP_PATH; ?>';
    </script>
    <script src='<?php echo $path . "/js/vendor/bootstrap.bundle.js" ?>'></script>

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
                            <h3 class="text-uppercase text-center">EASYTALK</h3>
                            <h4 id="salmon" class="text-center">Reset Password</h4>
                        </div>
                        <?php
                        $selector = sanitizeString($_GET["selector"] ?? '');
                        $validator = sanitizeString($_GET["validator"] ?? '');
                        if (empty($selector) || empty($validator)) {
                            //Invalid reset link (redirect to login)
                            header("location: login.php");
                            exit();
                        } else {
                            if (ctype_xdigit($selector) && ctype_xdigit($validator)) {
                            ?>
                        <form action="#" method="post" id="reset-password-form">
                            <input type="hidden" name="selector" value="<?php echo $selector; ?>">
                            <input type="hidden" name="validator" value="<?php echo $validator; ?>">
                            <input type="hidden" name="csrftoken"
                                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group mb-3">
                                <label for="user_password">Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="user_password" id="user_password"
                                        placeholder="Enter your new password" required="" minlength="8" maxlength="128">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('user_password','eye')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true" id="eye"></i></span>
                                        <span class="red" id="span_error_password"></span>
                                    </div>
                                </div>
                                <p></p>
                                <div class="form-group mb-3">
                                    <label for="confirm_password">Confirm Password</label>
                                    <div class="input-group bg-light">
                                        <input type="password" class="form-control" id="confirm_password" value=""
                                            name="confirm_password" placeholder="Re-enter your new password" required=""
                                            minlength="8" maxlength="128">
                                        <div class="input-group-addon">
                                            <span id="view-password" onclick="viewPassword('confirm_password', 'eye2')">
                                                <i class="fa fa-lg fa-eye eye-icon" aria-hidden="true" id="eye2"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group mb-0 text-center">
                                    <p><button class="app-btn app-btn-primary w-100" type="submit">
                                            <i class="fa-solid fa-refresh"></i>Reset Password
                                        </button>
                                    </p>
                                    <span class="muted pt-2">Back to <a href="login.php"
                                            class="theme-secondary-text">Login →</a>
                                    </span>
                                </div>
                            </div>
                        </form>
                        <?php
                            } else {
                                //Invalid reset link format (redirect to login)
                                header("location: login.php");
                                exit();
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require 'footer.php'; ?>