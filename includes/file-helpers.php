<?php
/* GUID-BASED FILE STORAGE SYSTEM
 
File and attachment utilities using GUID identifiers for enhanced security. */

require_once __DIR__ . '/db/attachments.php';
require_once __DIR__ . '/guid-utilities.php';
require_once __DIR__ . '/../config.php';

//Get file save path
function getFileSavePath(string $fileGuid, string $ext, string $chatType, array $chatContext): string
{
    //Convert hex format to standard GUID format if needed
    if (strlen($fileGuid) === 32 && ctype_xdigit($fileGuid)) {
        //Convert hex string to standard GUID format
        $fileGuid = substr($fileGuid, 0, 8) . '-' .
                   substr($fileGuid, 8, 4) . '-' .
                   substr($fileGuid, 12, 4) . '-' .
                   substr($fileGuid, 16, 4) . '-' .
                   substr($fileGuid, 20, 12);
    }

    if (!validateGuid($fileGuid)) {
        throw new InvalidArgumentException("Invalid file GUID format: $fileGuid");
    }

    $folderPath = getFolderPath($chatType, $chatContext);

    //Ensure directory exists
    if (!file_exists($folderPath)) {
        if (!mkdir($folderPath, 0777, true)) {
            throw new RuntimeException("Failed to create directory: $folderPath");
        }
    }

    return $folderPath . $fileGuid . "." . $ext;
}

//Get folder path based on chat type and GUID context
function getFolderPath(string $chatType, array $chatContext): string
{
    switch ($chatType) {
        case 'user_chat':
            if (!isset($chatContext['user_guid_1']) || !isset($chatContext['user_guid_2'])) {
                throw new InvalidArgumentException('User chat context requires user_guid_1 and user_guid_2');
            }
            return getUserChatFolder($chatContext['user_guid_1'], $chatContext['user_guid_2']);

        case 'group_chat':
        case 'group_profile':
            if (!isset($chatContext['group_guid'])) {
                throw new InvalidArgumentException('Group context requires group_guid');
            }
            return getGroupFolder($chatContext['group_guid']);

        case 'user_profile':
            if (!isset($chatContext['user_guid'])) {
                throw new InvalidArgumentException('User profile context requires user_guid');
            }
            return getUserProfileFolder($chatContext['user_guid']);

        default:
            throw new InvalidArgumentException("Unknown chat type: $chatType");
    }
}

//Get user chat folder path using GUID identifiers
function getUserChatFolder(string $user_guid1, string $user_guid2): string
{
    if (!validateGuid($user_guid1) || !validateGuid($user_guid2)) {
        throw new InvalidArgumentException('Invalid user GUID format');
    }

    $base = $GLOBALS['baseFilePath'];

    //Ensure consistent folder naming by sorting GUIDs lexicographically
    $guids = [$user_guid1, $user_guid2];
    sort($guids);

    return $base . "user_chats/user_{$guids[0]}_{$guids[1]}/";
}

//Get group folder path using GUID identifier
function getGroupFolder(string $groupGuid): string
{
    if (!validateGuid($groupGuid)) {
        throw new InvalidArgumentException("Invalid group GUID format: $groupGuid");
    }

    $base = $GLOBALS['baseFilePath'];
    return $base . "groups/group_{$groupGuid}/";
}

//Get user profile folder path using GUID identifier
function getUserProfileFolder(string $user_guid): string
{
    if (!validateGuid($user_guid)) {
        throw new InvalidArgumentException("Invalid user GUID format: $user_guid");
    }

    $base = $GLOBALS['baseFilePath'];
    return $base . "user_profiles/user_{$user_guid}/";
}

//Validate folder path to prevent directory traversal attacks
function validateFolderPath(string $path): bool
{
    $base = realpath($GLOBALS['baseFilePath']);
    $resolvedPath = realpath($path);

    //If real-path returns false, the path doesn't exist or is invalid
    if ($resolvedPath === false) {
        //For non-existent paths, check if they would be within base directory
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBase = str_replace('\\', '/', $base);

        //Check if path starts with base and doesn't contain traversal attempts
        if (strpos($normalizedPath, $normalizedBase) !== 0) {
            return false;
        }

        //Check for directory traversal patterns
        if (strpos($normalizedPath, '../') !== false || strpos($normalizedPath, '..\\') !== false) {
            return false;
        }

        return true;
    }

    //For existing paths, ensure they're within the base directory
    return strpos($resolvedPath, $base) === 0;
}

//Store group profile image using GUID identifier
function storeGroupProfile(string $fileGuid, string $ext, string $groupGuid): string
{
    return getFileSavePath($fileGuid, $ext, 'group_profile', [
        'group_guid' => $groupGuid
    ]);
}

//Get attachment URL and MIME type using GUID identifier
function getAttachmentUrlAndMime(?string $attachmentGuid): array
{
    if ($attachmentGuid === null) {
        return [null, null];
    }

    if (!validateGuid($attachmentGuid)) {
        app_log("Invalid attachment GUID format: $attachmentGuid");
        return [null, null];
    }

    list($metadata, $error) = getAttachmentByGuid($attachmentGuid);
    if ($error !== "" || !is_array($metadata)) {
        app_log("Failed to get attachment metadata: $error");
        return [null, null];
    }

    return [getRelativeDownloadPath() . $metadata['guid'], $metadata['mimetype']];
}

//Get relative download path for file URLs
function getRelativeDownloadPath(): string
{
    return 'download.php?guid=';
}