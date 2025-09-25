<?php
require 'protect.php';
$file2 = dirname(__FILE__) . "/includes/functions.inc.php";
require_once $file2;
if (isset($_REQUEST["file"])) {
    // Get parameters
    $file = urldecode($_REQUEST["file"]); // Decode URL-encoded string
    $file = filter_var($file, FILTER_SANITIZE_STRING);
    /* Check if the file name includes illegal characters
    like "../" using the regular expression */
    list($metadata, $error) = getAttachmentById($file);
    checkError($error);
    if (is_array($metadata))
        $filePath = getFileSavePath($file, $metadata['extension']);
    // Process download
    if (file_exists($filePath)) {
        // serve the file
        header('Content-type: ' . $metadata['mimetype']);
        header('Content-Disposition: inline; filename="' . $metadata['name'] . '"');
        // add a header so these files are cached
        // 10 years// override the header that prevents these from being cached
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + DOWNLOAD_LINK_INTERVAL));
        header('Cache-Control: public');
        readfile($filePath);
        exit();

    } else {
        header("HTTP/1.0 404 Not Found");
        die();

    }
}