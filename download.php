<?php
/* DOWNLOAD HANDLER

This file is the single entry point for serving all files stored on the server. 
Whenever the browser needs to display or download a file (image, video, audio, document), it makes a request to this file. */

try {
    require 'protect.php';
} catch (Exception $e) {
    app_log("download.php - protect.php failed: " . $e->getMessage());
    header("HTTP/1.0 401 Unauthorized");
    echo "Authentication required";
    exit();
}

require_once dirname(__FILE__) . "/includes/download/FileDownloadHandler.php";
require_once dirname(__FILE__) . "/includes/dbh.inc.php";

//Initialize handler
$handler = new FileDownloadHandler(getDbConnection());

//Route request based on type
if (isset($_GET['type']) && isset($_GET['guid'])) {
    $type = $_GET['type'];
    $guid = $_GET['guid'];

    switch ($type) {
        case 'profile':
            $handler->handleProfileImage($guid);
            break;

        case 'group':
            $handler->handleGroupImage($guid);
            break;
    }
}

//Handle attachment downloads.
if (isset($_REQUEST["guid"])) {
    $mode = $_REQUEST['mode'] ?? 'attachment';
    $handler->handleAttachment($_REQUEST["guid"], $mode);
}

//If no valid request parameters provided
app_log("download.php - No valid request parameters provided");
$handler->sendError(400, "Invalid request");