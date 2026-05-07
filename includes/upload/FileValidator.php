<?php
/* FILE VALIDATOR

Validates file uploads against configured restrictions. */

class FileValidator
{
    //Validate file against type and size restrictions
    public static function isFileValid(string $filename, ?int $filesize = null): array
    {
        $error = "";
        $ext = self::getFileExtension($filename);
        list($valid, $filetype) = self::isFileTypeAllowed($ext);
        
        if (!$valid) {
            $error = "This file type is not supported.";
            return [$valid, $filetype, $error];
        }
        
        //Check size limit
        if ($filesize === null || $filesize > MAX_FILE_SIZE) {
            $error = "This file size is not supported.";
            $valid = false;
            return [$valid, $filetype, $error];
        }

        return [$valid, $filetype, $error];
    }

    //Extract file extension from filename
    public static function getFileExtension(string $filename): string
    {
        $file_ext = @strtolower(@strrchr($filename, "."));
        if (@strpos($file_ext, '.') !== false) {
            $file_ext = @substr($file_ext, 1); // remove dot
        }
        return $file_ext;
    }

    //Get allowed file extensions from config
    public static function getAllowedExtensions(): array
    {
        return ALLOWED_FILE_EXTENSIONS;
    }

    //Check if file type is allowed
    public static function isFileTypeAllowed(string $file_ext): array
    {
        $result = false;
        $type = null;
        $allowedExtensions = self::getAllowedExtensions();

        if (count($allowedExtensions)) {
            if (isset($allowedExtensions[$file_ext])) {
                $result = true;
                $type = $allowedExtensions[$file_ext];
            }
        }
        
        return [$result, $type];
    }
}