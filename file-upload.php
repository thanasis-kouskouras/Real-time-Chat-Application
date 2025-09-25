<?php
require_once(__DIR__ . '/config.php');

function isFileValid($filename, $filesize = null): array
{
    //check type restriction
    $error = "";
    $ext = getFileExtension($filename);
    list($valid, $filetype) = isFileTypedAllowed($ext);
    if (!$valid) {
        $error = "This file type is not supported.";
        return array($valid, $filetype, $error);
    }
    //check size limit
    if ($filesize == null || $filesize > MAX_FILE_SIZE) {
        $error = "This file size is not supported.";
        $valid = false;
        return array($valid, $filetype, $error);
    }

    return array($valid, $filetype, $error);
}

function getFileExtension($filename): string
{
    $file_ext = @strtolower(@strrchr($filename, "."));
    if (@strpos($file_ext, '.') !== false) { 
        $file_ext = @substr($file_ext, 1); // remove dot
    }
    return $file_ext;
}
function getAllowedExtensions(): array
{
    return ALLOWED_FILE_EXTENSIONS;
}

function isFileTypedAllowed($file_ext): bool|array
{
    //check type restriction
    $result = false;
    $type = null;
    // Allowed file extensions. Will only allow these extensions if not empty.
    $allowedExtensions = getAllowedExtensions();

    // check file type if needed
    if (count($allowedExtensions)) {   
        echo "Checking file ext: $file_ext\n";
        if (isset($allowedExtensions[$file_ext])) {
            $result = true;
            $type = $allowedExtensions[$file_ext];
        }
    }
    return array($result, $type);
}