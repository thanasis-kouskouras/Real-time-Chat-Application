<?php
/* GET ONE-ON-ONE CHAT MESSAGES ENDPOINT
 
GET /api/chat-api.php?action=get-messages&friend_guid=X
 
Retrieves messages from a one-on-one chat conversation. */

require_once __DIR__ . '/../../includes/db/chatbox.php';
require_once __DIR__ . '/../../includes/encryption.php';
require_once __DIR__ . '/../api-response.php';
require_once __DIR__ . '/../../includes/guid-utilities.php';

//Rate limit (max 60 requests per minute)
checkRateLimit($user_guid, 'get_chat_messages', 60, 60);

$currentUserGuid = $user_guid;

$friendGuid = $_GET['friend_guid'] ?? null;
if (!$friendGuid) {
    sendError("friend_guid parameter is required", 400);
}

if (!validateGuid($friendGuid)) {
    sendError("Invalid friend_guid format. Must be a valid GUID.", 400);
}

//Get pagination parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$beforeMessageGuid = isset($_GET['before']) ? trim($_GET['before']) : null;

//Validate pagination parameters
if ($limit < 1 || $limit > 100) {
    $limit = 50;
}

if ($beforeMessageGuid !== null && !validateGuid($beforeMessageGuid)) {
    sendError("Invalid before parameter. Must be a valid GUID.", 400);
}

//Get chat messages
try {
    $conn = getDbConnection();

    //Verify friendship exists
    $friendshipStmt = $conn->prepare("
        SELECT 1 FROM addrequest
        WHERE ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true))
            OR (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))
        AND request_status = 'Confirm'
    ");

    if (!$friendshipStmt) {
        throw new RuntimeException('Database error: ' . mysqli_error($conn));
    }

    $friendshipStmt->bind_param("ssss", $currentUserGuid, $friendGuid, $friendGuid, $currentUserGuid);
    $friendshipStmt->execute();
    $friendshipResult = $friendshipStmt->get_result();
    $friendship = $friendshipResult->fetch_assoc();
    $friendshipStmt->close();

    if (!$friendship) {
        sendError("You are not friends with this user", 403);
    }

    //Get chat messages using cursor-based pagination
    list($rawMessages, $hasMore, $nextCursor, $error) = getMessagesCursorByGuid(
        $currentUserGuid,
        $friendGuid,
        $limit,
        $beforeMessageGuid
    );

    if ($rawMessages === false) {
        throw new RuntimeException($error);
    }

    $messages = [];
    $isEncrypted = $GLOBALS['isEncrypted'] ?? false;

    foreach ($rawMessages as $message) {
        $messageContent = $message['message_content'];

        //Decrypt if encrypted
        if ($isEncrypted && $messageContent) {
            try {
                $messageContent = decrypt($messageContent);
            } catch (Exception $e) {
                $messageContent = '[Encrypted message]';
            }
        }

        //Generate profile image URL for sender
        $senderProfileUrl = 'img/profiledefault.jpg';
        if (!empty($message['sender_image_guid']) && !empty($message['sender_image_path'])) {
            $guidHex = str_replace('-', '', $message['sender_image_guid']);
            $senderProfileUrl = 'download.php?guid=' . urlencode($guidHex) . '&type=profile';
        }

        $messages[] = [
            'message_guid' => $message['message_guid'],
            'from_guid' => $message['from_guid'],
            'to_guid' => $message['to_guid'],
            'sender_guid' => $message['sender_guid'],
            'sender_username' => $message['sender_username'],
            'sender_profile_url' => $senderProfileUrl,
            'message_content' => $messageContent,
            'created_at' => $message['created_at'],
            'status' => (int)$message['status'],
            'is_deleted' => (bool)$message['is_deleted'],
            'attachment_guid' => $message['attachment_guid'],
            'url' => $message['url'],
            'mime_type' => $message['mime_type'],
            'filename' => $message['filename']
        ];
    }

    //Get friend information for the header (only on initial load)
    $friendInfo = null;
    if ($beforeMessageGuid === null) {
        $friendStmt = $conn->prepare("
            SELECT
                bin_to_uuid(u.user_guid, true) as user_guid,
                u.user_username,
                u.user_status,
                bin_to_uuid(pi.image_guid, true) as image_guid,
                pi.file_path
            FROM users u
            LEFT JOIN profileImage pi ON u.user_guid = pi.user_guid AND pi.file_path IS NOT NULL
            WHERE u.user_guid = uuid_to_bin(?, true)
        ");

        if ($friendStmt) {
            $friendStmt->bind_param("s", $friendGuid);
            $friendStmt->execute();
            $friendResult = $friendStmt->get_result();
            $friendInfo = $friendResult->fetch_assoc();
            $friendStmt->close();
        }
    }
} catch (Exception $e) {
    app_log("Chat messages - Database error: " . $e->getMessage());
    sendError("Failed to retrieve messages", 500);
}

//Build response with pagination info
$responseData = [
    'messages' => $messages,
    'has_more' => $hasMore
];

//Add cursor for next page if available
if ($nextCursor !== null) {
    $responseData['next_cursor'] = $nextCursor;
}

//Add friend information if available (only on initial load)
if ($friendInfo) {
    //Generate profile image URL
    $friendProfileUrl = 'img/profiledefault.jpg';
    if (!empty($friendInfo['image_guid']) && !empty($friendInfo['file_path'])) {
        $guidHex = str_replace('-', '', $friendInfo['image_guid']);
        $friendProfileUrl = 'download.php?guid=' . urlencode($guidHex) . '&type=profile';
    }

    $responseData['friend'] = [
        'user_guid' => $friendInfo['user_guid'],
        'username' => $friendInfo['user_username'],
        'status' => $friendInfo['user_status'],
        'profile_url' => $friendProfileUrl
    ];
}

sendResponse(true, $responseData, 'Messages retrieved successfully', 200);