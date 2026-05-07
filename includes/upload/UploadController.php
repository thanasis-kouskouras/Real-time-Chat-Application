<?php
/* UPLOAD CONTROLLER

Handles file uploads:
1. Generate GUID and path
2. Process and save file
3. Store path in database
4. Return download URL */

require_once 'FileStorage.php';
require_once 'FileDatabase.php';
require_once 'MediaFileProcessor.php';

class UploadController
{
    private FileStorage $storage;
    private FileDatabase $database;
    private MediaFileProcessor $processor;

    public function __construct(mysqli $connection = null)
    {
        $this->storage = new FileStorage();
        $this->database = new FileDatabase($connection);
        $this->processor = new MediaFileProcessor();
    }

    //Upload profile image
    public function uploadProfileImage(string $userGuid, array $fileData): array
    {
        try {
            //Basic validation
            if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
                return $this->errorResponse('No valid file uploaded');
            }

            //Generate temporary path
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $tempPath = $this->storage->createProfileImagePath($userGuid, $extension);

            //Process image (resize and optimize)
            $processingResult = $this->processor->processProfileImage($fileData['tmp_name'], $tempPath);
            
            if (!$processingResult->success) {
                return $this->errorResponse('Failed to process image: ' . implode(', ', $processingResult->errors));
            }

            //Store in database
            $dbResult = $this->database->storeProfileImage($userGuid, $tempPath, $extension);
            
            if (!$dbResult->success) {
                //Cleanup file if database failed
                @unlink($tempPath);
                return $this->errorResponse('Failed to store image record: ' . $dbResult->errorMessage);
            }

            //Get the UUID from database result
            $imageGuid = $dbResult->fileGuid;
            $oldFilePath = $dbResult->metadata['old_file_path'] ?? null;
            
            //Rename file to use actual UUID
            $finalPath = $this->storage->getFinalFilePath($tempPath, $imageGuid);
            if (!rename($tempPath, $finalPath)) {
                return $this->errorResponse('Failed to finalize file path');
            }

            //Update database with final path
            $this->database->updateFilePath($imageGuid, $finalPath, 'profile');

            //Delete old file if it exists and is different from new file
            if ($oldFilePath && $oldFilePath !== $finalPath && file_exists($oldFilePath)) {
                if (@unlink($oldFilePath)) {
                    app_log("Deleted old profile image: $oldFilePath");
                } else {
                    app_log("Failed to delete old profile image: $oldFilePath");
                }
            }
            
            //Also check for any other old profile images in the uploads directory for this user
            $uploadsBaseDir = $GLOBALS['baseFilePath'] ?? __DIR__ . '/../../uploads/';
            $userProfileDir = rtrim($uploadsBaseDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('{guid}', $userGuid, UPLOAD_PATH_PROFILES);
            
            if (is_dir($userProfileDir)) {
                $files = glob($userProfileDir . '*');
                foreach ($files as $file) {
                    //Skip the new file just uploaded (check both absolute and relative paths)
                    $fileRealPath = realpath($file);
                    $finalPathReal = realpath($finalPath);
                    $tempPathReal = realpath($tempPath);

                    if (is_file($file) &&
                        $fileRealPath !== $finalPathReal &&
                        $fileRealPath !== $tempPathReal &&
                        $file !== $finalPath && 
                        $file !== $tempPath) {
                        if (@unlink($file)) {
                            app_log("Deleted orphaned profile image: $file");
                        } else {
                            app_log("Failed to delete orphaned profile image: $file");
                        }
                    }
                }
            }

            //Generate download URL
            $downloadUrl = $this->storage->generateDownloadUrl($imageGuid, 'profile');

            return [
                'success' => true,
                'data' => [
                    'file_guid' => $imageGuid,
                    'url' => $downloadUrl,
                    'file_path' => $finalPath,
                    'file_size' => $processingResult->processedSize,
                    'original_size' => $processingResult->originalSize,
                    'compression_ratio' => $processingResult->compressionRatio,
                    'replaced_old_image' => $oldFilePath !== null
                ],
                'message' => 'Profile image uploaded successfully'
            ];

        } catch (Exception $e) {
            app_log("Profile upload error: " . $e->getMessage());
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    //Upload chat media
    public function uploadChatMedia(string $userGuid, string $chatGuid, array $fileData): array
    {
        try {
            //Basic validation (handle both HTTP uploads and WebSocket binary data)
            if (!isset($fileData['tmp_name']) || !file_exists($fileData['tmp_name'])) {
                return $this->errorResponse('No valid file uploaded');
            }
            
            $isValidFile = is_uploaded_file($fileData['tmp_name']) || 
                          (file_exists($fileData['tmp_name']) && is_readable($fileData['tmp_name']));
            
            if (!$isValidFile) {
                return $this->errorResponse('No valid file uploaded');
            }

            //Generate temporary path
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $tempPath = $this->storage->createChatMediaPath($chatGuid, $extension);

            //Move uploaded file
            $moveSuccess = false;
            if (is_uploaded_file($fileData['tmp_name'])) {
                //HTTP upload (use move_uploaded_file)
                $moveSuccess = move_uploaded_file($fileData['tmp_name'], $tempPath);
            } else {
                //WebSocket temp file (use copy then unlink)
                $moveSuccess = copy($fileData['tmp_name'], $tempPath);
                if ($moveSuccess) {
                    @unlink($fileData['tmp_name']); //Clean up temp file
                }
            }
            
            if (!$moveSuccess) {
                return $this->errorResponse('Failed to save uploaded file');
            }

            /* Store in database.
            Determine MIME type from the file's actual magic bytes rather than the client-supplied $fileData['type']. */
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mimeType = $finfo ? finfo_file($finfo, $tempPath) : mime_content_type($tempPath);
            if ($finfo) { finfo_close($finfo); }

            //If the file is a WebM, trust the client to tell us whether it's audio or video (finfo can't always tell the two apart on its own).
            if ($mimeType === 'video/webm' || $mimeType === 'audio/webm') {
                $clientType = $fileData['type'] ?? null;
                if ($clientType === 'audio/webm' || $clientType === 'video/webm') {
                    $mimeType = $clientType;
                }
            }

            //Normalize MIME type
            $mimeType = $this->normalizeMimeType($mimeType, $extension);
            
            $metadata = [
                'original_name' => $fileData['name'],
                'mime_type' => $mimeType,
                'file_extension' => $extension
            ];

            $dbResult = $this->database->storeChatAttachment($userGuid, $tempPath, $metadata);
            
            if (!$dbResult->success) {
                //Cleanup file if database failed
                @unlink($tempPath);
                return $this->errorResponse('Failed to store attachment record: ' . $dbResult->errorMessage);
            }

            //Get the UUID from database result
            $fileGuid = $dbResult->fileGuid;
            
            //Rename file to use actual UUID
            $finalPath = $this->storage->getFinalFilePath($tempPath, $fileGuid);
            if (!rename($tempPath, $finalPath)) {
                return $this->errorResponse('Failed to finalize file path');
            }

            //Update database with final path
            $this->database->updateFilePath($fileGuid, $finalPath, 'attachment');

            //Generate download URL
            $downloadUrl = $this->storage->generateDownloadUrl($fileGuid, 'attachment');

            return [
                'success' => true,
                'data' => [
                    'file_guid' => $fileGuid,
                    'url' => $downloadUrl,
                    'file_path' => $finalPath,
                    'file_size' => filesize($finalPath)
                ],
                'message' => 'Chat media uploaded successfully'
            ];

        } catch (Exception $e) {
            app_log("Chat media upload error: " . $e->getMessage());
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    //Get profile image URL for user
    public function getProfileImageUrl(string $userGuid): string
    {
        return $this->database->getProfileImageUrl($userGuid);
    }

    //Get group image URL for group
    public function getGroupImageUrl(string $groupGuid): string
    {
        return $this->database->getGroupImageUrl($groupGuid);
    }

    //Delete profile image
    public function deleteProfileImage(string $userGuid): array
    {
        try {
            //Get current image info
            $sql = "SELECT bin_to_uuid(image_guid, true) as image_guid, file_path 
                    FROM profileImage 
                    WHERE user_guid = uuid_to_bin(?, true)
                    ORDER BY image_guid DESC LIMIT 1";
            
            $stmt = $this->database->getConnection()->prepare($sql);
            if (!$stmt) {
                return $this->errorResponse('Failed to prepare image lookup');
            }
            
            $stmt->bind_param("s", $userGuid);
            $stmt->execute();
            $result = $stmt->get_result();
            $imageInfo = $result->fetch_assoc();
            $stmt->close();
            
            if (!$imageInfo) {
                return $this->errorResponse('No profile image to delete');
            }
            
            //Delete the record from database
            $deleteSql = "DELETE FROM profileImage WHERE user_guid = uuid_to_bin(?, true)";
            $deleteStmt = $this->database->getConnection()->prepare($deleteSql);
            
            if (!$deleteStmt) {
                return $this->errorResponse('Failed to prepare deletion');
            }
            
            $deleteStmt->bind_param("s", $userGuid);
            
            if (!$deleteStmt->execute()) {
                $deleteStmt->close();
                return $this->errorResponse('Failed to delete image record');
            }
            
            $deleteStmt->close();
            
            //Delete the physical file if it exists
            if ($imageInfo['file_path'] && file_exists($imageInfo['file_path'])) {
                if (@unlink($imageInfo['file_path'])) {
                    app_log("Deleted profile image file: " . $imageInfo['file_path']);
                } else {
                    app_log("Failed to delete profile image file: " . $imageInfo['file_path']);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Profile image deleted successfully'
            ];
            
        } catch (Exception $e) {
            app_log("Profile image deletion error: " . $e->getMessage());
            return $this->errorResponse('Deletion failed: ' . $e->getMessage());
        }
    }

    //Upload group image
    public function uploadGroupImage(string $groupGuid, array $fileData): array
    {
        try {
            //Basic validation
            if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
                return $this->errorResponse('No valid file uploaded');
            }

            //Generate temporary path
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $tempPath = $this->storage->createGroupImagePath($groupGuid, $extension);

            //Process image (resize and optimize for group images)
            $processingResult = $this->processor->processGroupImage($fileData['tmp_name'], $tempPath);

            if (!$processingResult->success) {
                return $this->errorResponse('Failed to process image: ' . implode(', ', $processingResult->errors));
            }

            //Store in database
            $dbResult = $this->database->storeGroupImage($groupGuid, $tempPath, $extension);

            if (!$dbResult->success) {
                //Cleanup file if database failed
                @unlink($tempPath);
                return $this->errorResponse('Failed to store image record: ' . $dbResult->errorMessage);
            }

            //Get the UUID from database result
            $imageGuid = $dbResult->fileGuid;
            $oldFilePath = $dbResult->metadata['old_file_path'] ?? null;

            //Rename file to use actual UUID
            $finalPath = $this->storage->getFinalFilePath($tempPath, $imageGuid);
            if (!rename($tempPath, $finalPath)) {
                return $this->errorResponse('Failed to finalize file path');
            }

            //Update database with final path
            $this->database->updateGroupFilePath($imageGuid, $finalPath);

            //Delete old file if it exists and is different from new file
            if ($oldFilePath && $oldFilePath !== $finalPath && file_exists($oldFilePath)) {
                if (@unlink($oldFilePath)) {
                    app_log("Deleted old group image: $oldFilePath");
                } else {
                    app_log("Failed to delete old group image: $oldFilePath");
                }
            }

            //Also check for any other old group images in the uploads directory for this group
            $uploadsBaseDir = $GLOBALS['baseFilePath'] ?? __DIR__ . '/../../uploads/';
            $groupImageDir = rtrim($uploadsBaseDir, '/\\') . DIRECTORY_SEPARATOR . 'groups' . DIRECTORY_SEPARATOR . 'group_' . $groupGuid . DIRECTORY_SEPARATOR;

            if (is_dir($groupImageDir)) {
                $files = glob($groupImageDir . '*');
                foreach ($files as $file) {
                    //Skip the new file we just uploaded
                    $fileRealPath = realpath($file);
                    $finalPathReal = realpath($finalPath);
                    $tempPathReal = realpath($tempPath);

                    if (is_file($file) &&
                        $fileRealPath !== $finalPathReal &&
                        $fileRealPath !== $tempPathReal &&
                        $file !== $finalPath &&
                        $file !== $tempPath) {
                        if (@unlink($file)) {
                            app_log("Deleted orphaned group image: $file");
                        } else {
                            app_log("Failed to delete orphaned group image: $file");
                        }
                    }
                }
            }

            //Generate download URL
            $downloadUrl = $this->storage->generateDownloadUrl($imageGuid, 'group');

            return [
                'success' => true,
                'data' => [
                    'file_guid' => $imageGuid,
                    'url' => $downloadUrl,
                    'file_path' => $finalPath,
                    'file_size' => $processingResult->processedSize,
                    'original_size' => $processingResult->originalSize,
                    'compression_ratio' => $processingResult->compressionRatio,
                    'replaced_old_image' => $oldFilePath !== null
                ],
                'message' => 'Group image uploaded successfully'
            ];

        } catch (Exception $e) {
            app_log("Group image upload error: " . $e->getMessage());
            return $this->errorResponse('Upload failed: ' . $e->getMessage());
        }
    }

    //Normalize MIME type to fix common browser issues
    private function normalizeMimeType(string $mimeType, string $extension): string
    {
        //Map of incorrect MIME types to correct ones
        $mimeTypeMap = [
            'audio/mp3' => 'audio/mpeg',
            'audio/mpeg3' => 'audio/mpeg',
            'audio/x-mpeg-3' => 'audio/mpeg',
        ];

        //Check if MIME type needs correction
        if (isset($mimeTypeMap[$mimeType])) {
            return $mimeTypeMap[$mimeType];
        }

        /* Office formats are ZIP containers. 
        So, finfo correctly identifies them as application/zip. 
        Remap to the proper Office MIME based on extension so downstream consumers match. */
        if ($mimeType === 'application/zip') {
            $officeMimes = [
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ];
            $ext = strtolower($extension);
            if (isset($officeMimes[$ext])) {
                return $officeMimes[$ext];
            }
        }

        //If MIME type is missing or invalid, infer from extension
        if (empty($mimeType) || $mimeType === 'application/octet-stream') {
            $extensionMimeMap = [
                'mp3' => 'audio/mpeg',
                'mp4' => 'video/mp4',
                'webm' => 'video/webm',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
            ];
            
            $ext = strtolower($extension);
            if (isset($extensionMimeMap[$ext])) {
                return $extensionMimeMap[$ext];
            }
        }
        
        return $mimeType;
    }

    //Create error response
    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => [
                'message' => $message,
                'timestamp' => date('c')
            ]
        ];
    }
}