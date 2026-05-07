<?php
//Load .env file (must run before any constants are defined)
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad(); //safeLoad() does not throw if .env is missing

//DEBUG MODE CONFIGURTION
$debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
define('APP_DEBUG', $debug);

if($debug){
//Enable comprehensive error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
    ini_set('memory_limit', '256M');
    ini_set('html_errors', 1);
} else {
    //Suppress all error output and logging in production
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);
}

//Log helper, writes only when APP_DEBUG is true (no-op in production)
function app_log(string $message): void
{
    if (APP_DEBUG) {
        error_log($message);
    }
}

//DATABASE CONFIGURATION
define('DB_HOST',     $_ENV['DB_HOST']     ?? '127.0.0.1');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
define('DB_NAME',     $_ENV['DB_NAME']     ?? 'app_database');
define('DB_PORT',     (int)($_ENV['DB_PORT'] ?? 3306));
const DB_CHARSET = 'utf8mb4';

//WEBSOCKET CONFIGURATION
define('WS_SERVER_PORT', (int)($_ENV['WS_SERVER_PORT'] ?? 8082));
define('WS_HOST', $_ENV['WS_HOST'] ?? 'localhost');
define('WS_SERVER_API_KEY', $_ENV['WS_SERVER_API_KEY'] ?? ''); //WebSocket Server API Key for server-side connections

//Allowed Origins for WebSocket Connection
$_wsOriginsRaw = $_ENV['WS_ALLOWED_ORIGINS'] ?? 'localhost,127.0.0.1';
define('WS_ALLOWED_ORIGINS', array_values(array_filter(array_map('trim', explode(',', $_wsOriginsRaw)))));
unset($_wsOriginsRaw);

//EMAIL CONFIGURATION
define('MAIL_USERNAME',   $_ENV['MAIL_USERNAME']   ?? '');
define('MAIL_PASSWORD',   $_ENV['MAIL_PASSWORD']   ?? '');
define('MAIL_HOST',       $_ENV['MAIL_HOST']       ?? 'smtp.gmail.com');
define('MAIL_PORT',       (int)($_ENV['MAIL_PORT'] ?? 587));
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');

//Public privacy contact email (shown in Privacy Policy)
define('PRIVACY_EMAIL',   $_ENV['PRIVACY_EMAIL']   ?? '');

//URL CONFIGURATION
define('APP_PATH', $_ENV['APP_PATH'] ?? ''); //Must match the folder name under your web root (e.g. '/Real-time-Chat-Application' if cloned with default name)

$http = "http://";
if ((isset($_SERVER['HTTPS']))) {
    if ($_SERVER['HTTPS'] == "on") {
        $http = "https://";
    }
}
$urlServer = $http . "localhost/";
$urlServerNoSlash = $http . "localhost";
$rootUrl = $urlServer;
$query = "?";
$urlBase = $urlServer;
$url = $urlServer;

if ((isset($_SERVER['SERVER_NAME']))) {

    $uri = $_SERVER['REQUEST_URI'];
    if ($uri !== "/") {

        $uri2 = $uri;
        if (stristr($uri, 'php')) {
            $uri2 = explode("/", $uri)[1];
        }

        $slash = "/";
        if (str_starts_with($uri2, "/")) {
            $slash = ""; 
        }

        $url = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $slash . $uri2;
        $url = trim($url, "/");
        $urlBase = $url;       
    } 
    else {
        $url = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
        $urlBase = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
    }

    $query = "";

    if (stristr($url, '//'))
        $url = $http . str_replace('//', '/', $url);
    else {
        $url = $http . $url;
        $urlBase = $http . $urlBase;
    }

    $rootUrl = $urlBase;
    if (stristr($url, '?')) {
        $tmp = explode('?', $url);
        $rootUrl = $tmp[0];
        $query = "?" . $tmp[1];
    }

    if (stristr($rootUrl, 'views')) {
        $rootUrl = explode('views', $url)[0];
    }
}

//GLOBAL SETTINGS
$GLOBALS['isEncrypted'] = true;
$GLOBALS['url'] = $url;
$GLOBALS['rootUrl'] = $urlBase;
$GLOBALS['query'] = $query;
$GLOBALS['jwt_time'] = 60 * 60 * 24 * 30;
$GLOBALS['baseFilePath'] = __DIR__ . "/uploads/";

//SECURITY CONFIGURATION
$_sessionSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; //Session Cookie Security (applies globally before any session_start())
session_set_cookie_params([
    'lifetime' => 0,    //Session Cookie expires when browser closes
    'path'     => '/',
    'secure'   => $_sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
unset($_sessionSecure);

define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? '');
const JWT_ISSUER = "easytalk-app-issuer"; //JWT issuer identifier. If you fork this project, change to your own app name.
const JWT_AUD = "easytalk-app-aud"; //JWT audience identifier. If you fork this project, change to your own app name.
const JWT_ALGO = "HS512";

//TIME INTERVALS
const JWT_TIME = 60 * 60 * 2; //2 Hours
const PASSWORD_RESET_TIME_INTERVAL = 1200; //20 Minutes
const REMEMBER_ME_INTERVAL = 60 * 60 * 24 * 30; //30 Days
const VERIFICATION_TIME_INTERVAL = 60 * 60; //60 Minutes
const DOWNLOAD_LINK_INTERVAL = 60 * 60 * 24 * 365 * 10; //10 Years

//FILE UPLOAD CONFIGURATION
const MAX_FILE_SIZE = 50 * 1024 * 1024; //50MB

//Upload Path Configuration
const UPLOAD_PATH_PROFILES = 'profiles/user_profiles/user_{guid}/';
const UPLOAD_PATH_GROUPS = 'groups/group_{guid}/';
const UPLOAD_PATH_CHAT = 'chat/chat_{guid}/';

//Image Resize Configuration
const PROFILE_IMAGE_RESIZE_WIDTH = 500;
const PROFILE_IMAGE_RESIZE_HEIGHT = 500;
const PROFILE_IMAGE_JPEG_QUALITY = 85;
const GROUP_IMAGE_RESIZE_WIDTH = 300;
const GROUP_IMAGE_RESIZE_HEIGHT = 300;
const GROUP_IMAGE_JPEG_QUALITY = 85;
const CHAT_IMAGE_MAX_WIDTH = 1920;
const CHAT_IMAGE_MAX_HEIGHT = 1080;
const CHAT_IMAGE_JPEG_QUALITY = 80;
const THUMBNAIL_MAX_WIDTH = 200;
const THUMBNAIL_MAX_HEIGHT = 200;
const MAX_PROFILE_IMAGE_WIDTH = 500;
const MAX_PROFILE_IMAGE_HEIGHT = 500;
const PROFILE_IMAGE_QUALITY = 85; //JPEG quality (1-100)
const MAX_PROFILE_IMAGE_SIZE = 10000000;

//Allowed file extensions and their types
const ALLOWED_FILE_EXTENSIONS = [
    'avi' => 'video',
    'mov' => 'video',
    'mp4' => 'video',
    'webm' => 'video',
    'mp3' => 'audio',
    'ogg' => 'audio',
    'wmv' => 'audio',
    'jpg' => 'image',
    'jpeg' => 'image',
    'png' => 'image',
    'gif' => 'image',
    'txt' => 'document',
    'doc' => 'document',
    'docx' => 'document',
    'xls' => 'spreadsheet',
    'xlsx' => 'spreadsheet',
    'pdf' => 'document',
    'ppt' => 'presentation',
    'pptx' => 'presentation'
];

//OTHER CONFIGURATIONS
const DATE_FORMAT = 'H:i d-m-Y';
const USE_MAIL = "PhpMailer";
date_default_timezone_set('Europe/Athens');