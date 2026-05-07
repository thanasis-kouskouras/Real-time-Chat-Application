<?php
/* FILE DOWNLOAD HANDLER

Handles secure file downloads with caching, ETag support, and byte-range requests for media streaming. */

require_once dirname(__FILE__) . '/../functions.inc.php';
require_once dirname(__FILE__) . '/../file-helpers.php';
require_once dirname(__FILE__) . '/../guid-utilities.php';

class FileDownloadHandler
{
    private mysqli $connection;

    public function __construct(mysqli $connection)
    {
        $this->connection = $connection;
    }

    //Convert hex GUID to standard format
    public function hexToGuid(string $guidHex): string
    {
        if (strlen($guidHex) === 32) {
            return substr($guidHex, 0, 8) . '-' .
                   substr($guidHex, 8, 4) . '-' .
                   substr($guidHex, 12, 4) . '-' .
                   substr($guidHex, 16, 4) . '-' .
                   substr($guidHex, 20, 12);
        }
        return $guidHex;
    }

    //Validate file path for security
    private function validateFilePath(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            app_log("FileDownloadHandler - File does not exist: $filePath");
            return false;
        }

        if (!validateFolderPath(dirname($filePath))) {
            app_log("FileDownloadHandler - Invalid folder path: $filePath");
            return false;
        }

        return true;
    }

    //Get content type from file extension
    private function getContentType(string $filePath, ?string $defaultType = null): string
    {
        $pathInfo = pathinfo($filePath);
        $extension = strtolower($pathInfo['extension'] ?? '');

        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'weba' => 'audio/webm',
            'mp3'  => 'audio/mpeg',
            'ogg'  => 'audio/ogg',
            'opus' => 'audio/opus',
            'wav'  => 'audio/wav',
            'txt'  => 'text/plain',
        ];

        //For webm files, try to detect if it's audio or video using finfo
        if ($extension === 'webm' && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            if ($detectedType && strpos($detectedType, 'audio') !== false) {
                return 'audio/webm';
            }
        }

        return $mimeTypes[$extension] ?? ($defaultType ?? 'application/octet-stream');
    }

    //Send file with caching headers and range request support
    public function serveFile(string $filePath, array $options = []): void
    {
        if (!$this->validateFilePath($filePath)) {
            $this->sendError(403, "Access denied");
        }

        $fileName = $options['filename'] ?? basename($filePath);
        $contentType = $options['content_type'] ?? $this->getContentType($filePath);
        $cacheMaxAge = $options['cache_max_age'] ?? 31536000; //1 year default
        $disposition = $options['disposition'] ?? 'inline';

        //Calculate ETag
        $etag = md5_file($filePath);
        if ($etag === false) {
            app_log("FileDownloadHandler - Failed to calculate checksum: $filePath");
            $this->sendError(500, "File integrity check failed");
        }

        //Check if client has cached version
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
            header("HTTP/1.1 304 Not Modified");
            exit();
        }

        $fileSize = filesize($filePath);
        $start = 0;
        $end = $fileSize - 1;

        //Handle Range requests (needed for Chrome audio/video)
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];

            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
                $start = intval($matches[1]);
                $end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;

                //Validate range
                if ($start > $end || $start < 0 || $end >= $fileSize) {
                    header("HTTP/1.1 416 Range Not Satisfiable");
                    header("Content-Range: bytes */$fileSize");
                    exit();
                }

                $length = $end - $start + 1;

                //Send 206 Partial Content
                header("HTTP/1.1 206 Partial Content");
                header("Content-Range: bytes $start-$end/$fileSize");
                header('Content-Length: ' . $length);
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
                header('Accept-Ranges: bytes');
                header('Cache-Control: public, max-age=' . $cacheMaxAge);
                header('ETag: "' . $etag . '"');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($filePath)));

                //Stream the requested range
                $fp = fopen($filePath, 'rb');
                fseek($fp, $start);

                $buffer = 8192;
                $bytesLeft = $length;

                while ($bytesLeft > 0 && !feof($fp)) {
                    $bytesToRead = min($buffer, $bytesLeft);
                    echo fread($fp, $bytesToRead);
                    $bytesLeft -= $bytesToRead;
                    flush();
                }

                fclose($fp);
                exit();
            }
        }

        //Send full file
        header('HTTP/1.1 200 OK');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: ' . $disposition . '; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: public, max-age=' . $cacheMaxAge);
        header('ETag: "' . $etag . '"');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($filePath)));
        header('Accept-Ranges: bytes');

        readfile($filePath);
        exit();
    }

    //Serve default image
    public function serveDefaultImage(string $type = 'profile'): void
    {
        $defaultImages = [
            'profile' => __DIR__ . '/../../img/profiledefault.jpg',
            'group' => __DIR__ . '/../../img/groupdefault.png',
        ];

        $imagePath = $defaultImages[$type] ?? $defaultImages['profile'];

        header('Content-Type: ' . $this->getContentType($imagePath));
        header('Cache-Control: public, max-age=86400'); // 1 day
        readfile($imagePath);
        exit();
    }

    //Send error response
    public function sendError(int $code, string $message): void
    {
        $statusMessages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            500 => 'Internal Server Error',
        ];

        $status = $statusMessages[$code] ?? 'Error';
        header("HTTP/1.0 $code $status");
        echo $message;
        exit();
    }

    //Handle profile image download
    public function handleProfileImage(string $guidHex): void
    {
        app_log("FileDownloadHandler - Profile image request: $guidHex");

        $guid = $this->hexToGuid($guidHex);

        $stmt = $this->connection->prepare(
            "SELECT pi.image_type, pi.file_path, bin_to_uuid(pi.user_guid, true) as user_guid
             FROM profileImage pi
             WHERE pi.image_guid = uuid_to_bin(?, true)"
        );
        $stmt->bind_param("s", $guid);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $filePath = $row['file_path'] ?? null;

            if ($filePath && file_exists($filePath)) {
                $contentType = 'image/' . ($row['image_type'] === 'jpg' ? 'jpeg' : $row['image_type']);
                $this->serveFile($filePath, [
                    'content_type' => $contentType,
                    'cache_max_age' => 31536000, // 1 year
                ]);
            }
        }

        $this->serveDefaultImage('profile');
    }

    //Handle group image download
    public function handleGroupImage(string $guidHex): void
    {
        app_log("FileDownloadHandler - Group image request: $guidHex");

        $guid = $this->hexToGuid($guidHex);

        //Query using group_image column which contains the filename (GUID.extension)
        $stmt = $this->connection->prepare(
            "SELECT group_image, bin_to_uuid(group_guid, true) as group_guid
             FROM group_chats
             WHERE group_image IS NOT NULL"
        );
        $stmt->execute();
        $result = $stmt->get_result();

        //Find the group whose filename matches the requested file GUID
        while ($row = $result->fetch_assoc()) {
            $filename  = $row['group_image'];
            $groupGuid = $row['group_guid'];

            //Extract GUID from filename
            $fileGuidFromFilename = pathinfo($filename, PATHINFO_FILENAME);

            if ($fileGuidFromFilename === $guid) {
                //Construct file path: uploads/groups/group_GROUPGUID/FILEGUID.ext
                $groupFolder = $GLOBALS['baseFilePath'] . str_replace('{guid}', $groupGuid, UPLOAD_PATH_GROUPS);
                $filePath    = $groupFolder . '/' . $filename;

                if (file_exists($filePath)) {
                    $this->serveFile($filePath, [
                        'cache_max_age' => 31536000, //1 year
                        'disposition' => 'inline'
                    ]);
                    return;
                }
            }
        }

        app_log("FileDownloadHandler - Group image not found for GUID: $guid");
        $this->serveDefaultImage('group');
    }

    //Handle attachment download
    public function handleAttachment(string $guidHex, string $mode = 'attachment'): void
    {
        app_log("FileDownloadHandler - Attachment request: $guidHex, mode: $mode");

        $guid = $this->hexToGuid($guidHex);

        //Validate GUID format
        if (!validateGuid($guid)) {
            $this->sendError(400, "Invalid GUID format");
        }

        //Get attachment metadata
        list($metadata, $error) = getAttachmentByGuid($guid);

        if ($error || !is_array($metadata)) {
            $this->sendError(404, "Attachment not found");
        }

        $filePath = $metadata['file_path'] ?? null;

        if ($filePath && file_exists($filePath)) {
            //Determine disposition based on mode
            $disposition = ($mode === 'inline') ? 'inline' : 'attachment';

            $this->serveFile($filePath, [
                'filename' => $metadata['name'] ?? basename($filePath),
                'content_type' => $metadata['mimetype'] ?? null,
                'disposition' => $disposition,
            ]);
        }

        $this->sendError(404, "File not found");
    }
}