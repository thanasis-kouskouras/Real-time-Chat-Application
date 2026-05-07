<?php
require_once 'config.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/functions.inc.php";

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
    <title>EasyTalk - Sign Up</title>

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
    <script src='<?php echo $path . "/js/modules/privacy-policy-modal.js" ?>'></script>

    <!-- Make APP_PATH available to JavaScript for proper redirects -->
    <script>
    window.APP_PATH = '<?php echo APP_PATH; ?>';
    </script>

    <link rel="stylesheet"
        href='<?php echo $path . "/css/com_ajax_libs_twitter-bootstrap_5.0.0-beta1_css_bootstrap.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/fontawesome-all.6.4.0.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/buttons.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/loading-indicator.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/toast-notifications.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/header.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/mobile.css" ?>' />
    <link rel="stylesheet" href='<?php echo $path . "/css/privacy-policy.css" ?>' />
</head>

<body class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <img class="logo-signup" src="img/logo.png" alt="Logo">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4 col-xl-3">
                <div class="card p-4">
                    <div class="card-body">

                        <div class="text-center m-auto">
                            <h2 class="text-uppercase text-center">Sign Up</h2>
                        </div>

                        <form action="#" method="post" id="signup-form">
                            <input type="hidden" name="csrftoken"
                                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group mb-3">
                                <label for="user_username">Username</label>
                                <?php
                            if (isset($_GET["username"])) {
                                $username = sanitizeString($_GET["username"]);
                                echo '<input id="user_username" type="text" name="user_username" minlength="1" maxlength="30" placeholder="Enter your username" class="form-control" required="" value="' . $username . '">';
                            } else {
                                echo '<input id="user_username" type="text" name="user_username" minlength="1"
                                        maxlength="30" placeholder="Enter your username" class="form-control" required="">';
                                echo '<span class="red" id="span_error_username"></span>';
                            }
                            ?>
                            </div>
                            <div class="form-group mb-3">
                                <label for="user_email">Email</label>
                                <?php
                            if (isset($_GET["email"])) {
                                $email = sanitizeEmail($_GET["email"]);
                                echo '<input id="user_email" type="email" name="user_email" maxlength="254" placeholder="Enter your email address" class="form-control" required="" value="' . $email . '">';
                            } else {
                                echo '<input id="user_email" type="email" name="user_email" placeholder="Enter your email address" class="form-control" maxlength="254" required="">';
                                echo '<span class="red" id="span_error_email"></span>';
                            }
                            ?>
                            </div>
                            <div class="form-group mb-3">
                                <label for="user_password">Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="user_password" id="user_password"
                                        placeholder="Enter your password" minlength="8" maxlength="128" required="">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('user_password','eye')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true" id="eye"></i></span>
                                        <span class="red" id="span_error_password"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="confirm_password">Confirm Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" id="confirm_password" value=""
                                        name="confirm_password" placeholder="Re-enter your password" minlength="8"
                                        maxlength="128" required="">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('confirm_password','eye2')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true"
                                                id="eye2"></i></span>
                                        <span class="red" id="span_error_password"></span>
                                    </div>
                                </div>
                                <span class="red" id="span_error_confirm-password"></span>
                            </div>

                            <p class="text-muted small text-center mb-3">
                                By signing up, you accept our
                                <a href="privacy-policy.php" data-privacy-link>Privacy Policy</a>.
                            </p>

                            <div class="form-group mb-0 text-center">
                                <button id="submit_signup" class="app-btn app-btn-primary w-100" type="submit"
                                    name="signup-submit">
                                    <i class="fa-solid fa-user-plus"></i>Sign Up
                                </button>
                            </div>
                        </form>
                        <p></p>
                        <div class="text-center">
                            <p class="muted">Already have an account? <a href="login.php"
                                    class="theme-secondary-text">Login →</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>

</html>