<?php

/* FILE STORAGE SYSTEM
 
Generates file paths once and stores them in database. */

class FileStorage
{
    private string $baseUploadPath;

    public function __construct(string $baseUploadPath = null)
    {
        $this->baseUploadPath = $baseUploadPath ?? ($GLOBALS['baseFilePath'] ?? 'uploads/');
        $this->baseUploadPath = rtrim($this->baseUploadPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    //Generate and create profile image path (with temporary filename)
    public function createProfileImagePath(string $userGuid, string $extension): string
    {
        $this->validateGuid($userGuid);
        
        //Use configurable path from config.php
        $pathTemplate = UPLOAD_PATH_PROFILES;
        $userPath = str_replace('{guid}', $userGuid, $pathTemplate);
        $userFolder = $this->baseUploadPath . $userPath;
        
        //Create directory if it doesn't exist
        if (!is_dir($userFolder)) {
            if (!mkdir($userFolder, 0755, true)) {
                throw new RuntimeException("Failed to create directory: $userFolder");
            }
        }
        
        //Use temporary filename (will be renamed after getting UUID from database)
        return $userFolder . 'temp_' . uniqid() . '.' . $extension;
    }

    //Generate final file path with actual UUID
    public function getFinalFilePath(string $tempPath, string $uuid): string
    {
        $directory = dirname($tempPath);
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
        return $directory . DIRECTORY_SEPARATOR . $uuid . '.' . $extension;
    }

    //Generate and create chat media path (with temporary filename)
    public function createChatMediaPath(string $chatGuid, string $extension): string
    {
        $this->validateGuid($chatGuid);
        
        //Use configurable path from config.php
        $pathTemplate = UPLOAD_PATH_CHAT;
        $chatPath = str_replace('{guid}', $chatGuid, $pathTemplate);
        $chatFolder = $this->baseUploadPath . $chatPath;
        
        //Create directory if it doesn't exist
        if (!is_dir($chatFolder)) {
            if (!mkdir($chatFolder, 0755, true)) {
                throw new RuntimeException("Failed to create directory: $chatFolder");
            }
        }
        
        //Use temporary filename (will be renamed after getting UUID from database)
        return $chatFolder . 'temp_' . uniqid() . '.' . $extension;
    }

    //Generate and create group image path (with temporary filename)
    public function createGroupImagePath(string $groupGuid, string $extension): string
    {
        $this->validateGuid($groupGuid);
        
        //Use configurable path from config.php
        $pathTemplate = UPLOAD_PATH_GROUPS;
        $groupPath = str_replace('{guid}', $groupGuid, $pathTemplate);
        $groupFolder = $this->baseUploadPath . $groupPath;
        
        //Create directory if it doesn't exist
        if (!is_dir($groupFolder)) {
            if (!mkdir($groupFolder, 0755, true)) {
                throw new RuntimeException("Failed to create directory: $groupFolder");
            }
        }
        
        //Use temporary filename (will be renamed after getting UUID from database)
        return $groupFolder . 'temp_' . uniqid() . '.' . $extension;
    }

    //Generate download URL from file GUID
    public function generateDownloadUrl(string $fileGuid, string $fileType = 'file'): string
    {
        $this->validateGuid($fileGuid);
        
        //URL format: download.php?guid={guid}&type={type}
        $guidHex = str_replace('-', '', $fileGuid);
        $url = 'download.php?guid=' . urlencode($guidHex) . '&type=' . urlencode($fileType);
        
        return $url;
    }

    //Validate GUID format
    private function validateGuid(string $guid): void
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $guid)) {
            throw new InvalidArgumentException("Invalid GUID format: $guid");
        }
    }
}