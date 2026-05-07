<?php
require "includes/functions.inc.php";

//Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

//Generate CSRF token per session (shared with signup)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

//Handle redirect logic
if (!empty($_GET['redirect'])) {
    //Explicit redirect from email links (store only valid relative paths)
    $redirect = $_GET['redirect'];
    if (preg_match('/^\/[^\/]/', $redirect) || $redirect === '/') {
        $_SESSION['redirect_after_login'] = $redirect;
        app_log("LOGIN PAGE: Storing redirect in session from GET param: " . $redirect);
    }
} elseif (empty($_GET['from_protected'])) {
    //User came to login page directly (not redirected from protected page)
    if (isset($_SESSION['redirect_after_login'])) {
        app_log("LOGIN PAGE: Clearing stale redirect: " . $_SESSION['redirect_after_login']);
        unset($_SESSION['redirect_after_login']); //Clear any stale redirect to prevent random page redirects
    }
} else {
    //'from_protected=1' marker exists (user was just redirected from a protected page)
    app_log("LOGIN PAGE: Keeping redirect from protected page: " . ($_SESSION['redirect_after_login'] ?? 'none'));
}

list($user, $error) = is_user_logged_in();

if ($error !== ""){
    setSessionError($error);
}

$_isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

if ($user === false) {
//Not logged in
    setcookie("jwt", '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $_isSecure, 'httponly' => true, 'samesite' => 'Lax']);
} else {
//Already logged in
    header("Location: index.php");
    setcookie("jwt", '', ['expires' => time() - 3600, 'path' => '/', 'secure' => $_isSecure, 'httponly' => true, 'samesite' => 'Lax']);
    exit();
}

$path = $GLOBALS['rootUrl'] . '/static';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="img/logo.png" />
    <title>EasyTalk - Login</title>

    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">

    <script src='<?php echo $path . "/js/vendor/jquery.min.js" ?>'></script>
    <script src='<?php echo $path . "/js/modules/loading-indicator.js" ?>'></script>
    <script src='<?php echo $path . "/js/config/apiConfig.js" ?>'></script>
    <script src='<?php echo $path . "/js/auth.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-handler.js" ?>'></script>
    <script src='<?php echo $path . "/js/form-utilities.js" ?>'></script>
    <script src='<?php echo $path . "/js/auth-page.js" ?>'></script>
    <script type="module" src='<?php echo $path . "/js/modules/toast-notifications.js" ?>'></script>

    <!--Make APP_PATH available to JavaScript for proper redirects-->
    <script>
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
</head>

<body class="d-flex vw-100 responsive-height align-items-center justify-content-center">
    <div class="container mt-5 pt-5 mb-5">
        <div class="row justify-content-center">
            <img class="logo-login" src="img/logo.png" alt="Logo">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4 col-xl-3">
                <div class="card p-4">
                    <div class="card-body">
                        <div class="text-center m-auto">
                            <h2 class="text-uppercase text-center">LOGIN</h2>
                        </div>
                        <form action="#" method="post" id="login-form">
                            <input type="hidden" name="csrftoken"
                                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group mb-3">
                                <label for="user_email">Email</label>
                                <?php
                            if (isset($_GET["email"])) {
                                $email = sanitizeEmail($_GET["email"]);
                                echo '<input id="user_email" type="email" name="user_email" maxlength="254" placeholder="Enter your email address" class="form-control" required="" value="' . $email . '">';
                            } else {
                                echo '<input id="user_email" type="email" name="user_email" maxlength="254" placeholder="Enter your email address" class="form-control" required="">';
                            }
                            ?>
                            </div>
                            <div class="form-group mb-3">
                                <label for="user_password">Password</label>
                                <div class="input-group bg-light">
                                    <input type="password" class="form-control" name="user_password" id="user_password"
                                        placeholder="Enter your password" maxlength="128" required="">
                                    <div class="input-group-addon">
                                        <span id="view-password" onclick="viewPassword('user_password')"><i
                                                class="fa fa-lg fa-eye eye-icon" aria-hidden="true" id="eye"></i></span>
                                    </div>
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
                                <button class="app-btn app-btn-primary w-100" type="submit" name="login-submit">
                                    <i class="fa-solid fa-right-to-bracket"></i>Log In
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>