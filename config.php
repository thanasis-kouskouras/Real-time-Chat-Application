<?php

$http = "http://";
if ((isset($_SERVER['HTTPS']))) {
    if ($_SERVER['HTTPS'] == "on") {
        $http = "https://";
    }
}
$urlServer = $http . "localhost/Real-time-Chat-Application/"; //Change if deployed elsewhere
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
        if (str_starts_with($uri2, "/"))
            $slash = "";
        $url = $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $slash . $uri2;
        $url = trim($url, "/");
        $urlBase = $url;

    } else {
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
    if (stristr($rootUrl, 'views'))
        $rootUrl = explode('views', $url)[0];
}
$GLOBALS['isEncrypted'] = true;
$GLOBALS['url'] = $url;
$GLOBALS['rootUrl'] = $urlBase;
$GLOBALS['query'] = $query;
$GLOBALS['jwt_time'] = 60 * 60 * 24 * 30;
$GLOBALS['baseFilePath'] = __DIR__ . "/uploads/";
const JWT_SECRET = "your_jwt_secret_here"; //change this in production
const JWT_ISSUER = "your_issuer_here"; //change this in production
const JWT_AUD = "your_audience_here"; //change this in production
const JWT_ALGO = "HS512";

//#ALL INTERVALS IN APP
const JWT_TIME = 60 * 60 * 2;// 2Hours
const PASSWORD_RESET_TIME_INTERVAL = 1200; //60*20(20 MINUTES)
const REMEMBER_ME_INTERVAL = 60 * 60 * 24 * 30; //(30 DAYS)
const VERIFICATION_TIME_INTERVAL = 60 * 60; //60 MINUTES

const DOWNLOAD_LINK_INTERVAL = 60 * 60 * 24 * 365 * 10; //(10 YEARS)

const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB

// Message show time format
const DATE_FORMAT = 'H:i d-m-Y';
// #Mail Config
const USE_MAIL = "PhpMailer"; // or PhpMailer

//date_default_timezone_set();
date_default_timezone_set('Europe/Athens');
// Allowed file extensions and their types
const ALLOWED_FILE_EXTENSIONS = [
    'avi' => 'video',
    'mov' => 'video',
    'mp4' => 'video',
    'mp3' => 'audio',
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