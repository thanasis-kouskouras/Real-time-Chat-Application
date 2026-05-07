<?php

require_once 'DatabaseResult.php';
require_once dirname(__FILE__) . '/../guid-utilities.php';

/* FILE DATABASE MANAGER

Stores and retrieves file paths from database. */

class FileDatabase
{
    private mysqli $connection;

    public function __construct(mysqli $connection = null)
    {
        $this->connection = $connection ?? $this->getDbConnection();
    }

    /* Store profile image record (database generates UUID).
    Returns old file path if updating existing record. */
    public function storeProfileImage(string $userGuid, string $filePath, string $extension): DatabaseResult
    {
        //Get the old file path if it exists (for cleanup)
        $oldFilePath = null;
        $checkSql = "SELECT file_path FROM profileImage WHERE user_guid = uuid_to_bin(?, true)";
        $checkStmt = $this->connection->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("s", $userGuid);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkRow = $checkResult->fetch_assoc()) {
                $oldFilePath = $checkRow['file_path'];
            }
            $checkStmt->close();
        }
        
        /*Insert without specifying image_guid (let database generate it).
        ON DUPLICATE KEY UPDATE will update existing record if user_guid already exists. */
        $sql = "INSERT INTO profileImage (user_guid, image_type, file_path) 
                VALUES (uuid_to_bin(?, true), ?, ?)
                ON DUPLICATE KEY UPDATE 
                    image_type = VALUES(image_type),
                    file_path = VALUES(file_path)";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return DatabaseResult::failure("Failed to prepare profile image insert: " . $this->connection->error);
        }
        
        $stmt->bind_param("sss", $userGuid, $extension, $filePath);
        
        if (!$stmt->execute()) {
            $error = "Failed to store profile image record: " . $stmt->error;
            $stmt->close();
            return DatabaseResult::failure($error);
        }
        
        //Get the generated UUID
        $getUuidSql = "SELECT bin_to_uuid(image_guid, true) as image_guid 
                       FROM profileImage 
                       WHERE user_guid = uuid_to_bin(?, true) 
                       ORDER BY image_guid DESC LIMIT 1";
        
        $uuidStmt = $this->connection->prepare($getUuidSql);
        if (!$uuidStmt) {
            $stmt->close();
            return DatabaseResult::failure("Failed to retrieve generated UUID");
        }
        
        $uuidStmt->bind_param("s", $userGuid);
        $uuidStmt->execute();
        $result = $uuidStmt->get_result();
        $row = $result->fetch_assoc();
        
        $stmt->close();
        $uuidStmt->close();
        
        if (!$row) {
            return DatabaseResult::failure("Failed to retrieve generated UUID");
        }
        
        return DatabaseResult::success($row['image_guid'], [
            'file_path' => $filePath,
            'old_file_path' => $oldFilePath
        ]);
    }

    //Store chat attachment record (database generates UUID)
    public function storeChatAttachment(string $userGuid, string $filePath, array $metadata): DatabaseResult
    {
        //Generate a new GUID for the attachment
        $attachmentGuid = generateGuid();
        
        //Insert with the generated GUID
        $sql = "INSERT INTO attachments (guid, name, mime_type, file_extension, chat_type, 
                user_guid_1, file_path) 
                VALUES (uuid_to_bin(?, true), ?, ?, ?, 'user_chat', uuid_to_bin(?, true), ?)";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return DatabaseResult::failure("Failed to prepare attachment insert: " . $this->connection->error);
        }
        
        $originalName = $metadata['original_name'] ?? basename($filePath);
        $mimeType = $metadata['mime_type'] ?? 'application/octet-stream';
        $extension = $metadata['file_extension'] ?? pathinfo($filePath, PATHINFO_EXTENSION);
        
        $stmt->bind_param("ssssss", $attachmentGuid, $originalName, $mimeType, $extension, $userGuid, $filePath);
        
        if (!$stmt->execute()) {
            $error = "Failed to store attachment record: " . $stmt->error;
            $stmt->close();
            return DatabaseResult::failure($error);
        }
        
        $stmt->close();
        
        return DatabaseResult::success($attachmentGuid, [
            'file_path' => $filePath
        ]);
    }

    //Get profile image URL for user
    public function getProfileImageUrl(string $userGuid): string
    {
        $sql = "SELECT bin_to_uuid(image_guid, true) as image_guid, file_path 
                FROM profileImage 
                WHERE user_guid = uuid_to_bin(?, true) AND file_path IS NOT NULL
                ORDER BY image_guid DESC LIMIT 1";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return 'img/profiledefault.jpg';
        }
        
        $stmt->bind_param("s", $userGuid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            
            //Generate download URL
            if (!empty($row['file_path'])) {
                $guidHex = str_replace('-', '', $row['image_guid']);
                return 'download.php?guid=' . urlencode($guidHex) . '&type=profile';
            }
        }
        
        $stmt->close();
        return 'img/profiledefault.jpg';
    }

    /* Store group image record (database generates UUID)
    Returns old file path if updating existing record. */
    public function storeGroupImage(string $groupGuid, string $filePath, string $extension): DatabaseResult
    {
        //Get the old file path if it exists (for cleanup)
        $oldFilePath = null;
        $checkSql = "SELECT file_path FROM group_chats WHERE group_guid = uuid_to_bin(?, true)";
        $checkStmt = $this->connection->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param("s", $groupGuid);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkRow = $checkResult->fetch_assoc()) {
                $oldFilePath = $checkRow['file_path'];
            }
            $checkStmt->close();
        }

        // Update group with file path and group_image (let database generate image_guid)
        $sql = "UPDATE group_chats
                SET file_path = ?, image_guid = UUID_TO_BIN(UUID(), true), group_image = ?
                WHERE group_guid = uuid_to_bin(?, true)";

        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return DatabaseResult::failure("Failed to prepare group image update: " . $this->connection->error);
        }

        //For group_image, store just the filename (will be updated later with the final GUID-based name)
        $tempFilename = basename($filePath);
        $stmt->bind_param("sss", $filePath, $tempFilename, $groupGuid);

        if (!$stmt->execute()) {
            $error = "Failed to store group image record: " . $stmt->error;
            $stmt->close();
            return DatabaseResult::failure($error);
        }

        //Get the generated UUID
        $getUuidSql = "SELECT bin_to_uuid(image_guid, true) as image_guid
                       FROM group_chats
                       WHERE group_guid = uuid_to_bin(?, true) AND file_path = ?
                       LIMIT 1";

        $uuidStmt = $this->connection->prepare($getUuidSql);
        if (!$uuidStmt) {
            $stmt->close();
            return DatabaseResult::failure("Failed to retrieve generated UUID");
        }

        $uuidStmt->bind_param("ss", $groupGuid, $filePath);
        $uuidStmt->execute();
        $result = $uuidStmt->get_result();
        $row = $result->fetch_assoc();

        $stmt->close();
        $uuidStmt->close();

        if (!$row) {
            return DatabaseResult::failure("Failed to retrieve generated UUID");
        }

        return DatabaseResult::success($row['image_guid'], [
            'file_path' => $filePath,
            'old_file_path' => $oldFilePath
        ]);
    }

    //Get group image URL for group
    public function getGroupImageUrl(string $groupGuid): string
    {
        $sql = "SELECT group_image 
                FROM group_chats 
                WHERE group_guid = uuid_to_bin(?, true) AND group_image IS NOT NULL
                LIMIT 1";
        
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            return 'img/groupdefault.png';
        }
        
        $stmt->bind_param("s", $groupGuid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            
            //Extract GUID from filename (format: GUID.extension)
            if (!empty($row['group_image'])) {
                $filename = $row['group_image'];
                //Remove extension to get GUID
                $guidWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                
                //Convert to hex format (remove dashes)
                $guidHex = str_replace('-', '', $guidWithoutExt);
                
                //Return download URL for group profile image
                return 'download.php?guid=' . urlencode($guidHex) . '&type=group';
            }
        }
        
        $stmt->close();
        return 'img/groupdefault.png';
    }

    //Update file path after renaming
    public function updateFilePath(string $fileGuid, string $newPath, string $fileType): bool
    {
        if ($fileType === 'profile') {
            $sql = "UPDATE profileImage SET file_path = ? WHERE image_guid = uuid_to_bin(?, true)";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("ss", $newPath, $fileGuid);
        } elseif ($fileType === 'group') {
            //Update group_image field with the new filename
            $newFilename = basename($newPath);
            $sql = "UPDATE group_chats SET file_path = ?, group_image = ? WHERE image_guid = uuid_to_bin(?, true)";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("sss", $newPath, $newFilename, $fileGuid);
        } else {
            $sql = "UPDATE attachments SET file_path = ? WHERE guid = uuid_to_bin(?, true)";
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param("ss", $newPath, $fileGuid);
        }

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    //Update group file path after renaming
    public function updateGroupFilePath(string $fileGuid, string $newPath): bool
    {
        return $this->updateFilePath($fileGuid, $newPath, 'group');
    }

    //Get database connection
    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    //Fallback function (get connection from global function if none was injected)
    private function getDbConnection(): mysqli
    {
        if (function_exists('getDbConnection')) {
            return getDbConnection();
        }
        throw new RuntimeException("Database connection not available");
    }
}