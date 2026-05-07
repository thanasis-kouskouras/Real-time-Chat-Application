<?php
/* GET MESSAGES ENDPOINT

Handles GET requests to retrieve messages from a group chat. */

//Verify request method
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Rate limit (max 60 group message requests per minute)
checkRateLimit($user_guid, 'group_messages', 60, 60);

//Get query parameters
if (!isset($_GET['group_guid'])) {
    sendError("Missing required parameter: group_guid", 400);
}

$groupGuid = trim($_GET['group_guid']);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$beforeMessageGuid = isset($_GET['before']) ? trim($_GET['before']) : null;

//offset=0 is the default for the initial page load (no cursor yet)
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : null;
$useCursor = ($beforeMessageGuid !== null || $offset === null);

//Validate group_guid format
if (!isValidGuid($groupGuid)) {
    sendError("Invalid group_guid format", 400);
}

//Validate pagination parameters
if ($limit < 1 || $limit > 100) {
    $limit = 50;
}

if ($offset !== null && $offset < 0) {
    $offset = 0;
}

//Verify user is a group member
if (!isGroupMemberByGuid($groupGuid, $user_guid)) {
    sendError("You are not a member of this group", 403);
}

//Get messages using cursor-based pagination (load more) or offset-based (initial load)
if ($useCursor) {
    list($messages, $error) = getGroupMessagesCursorByGuid($groupGuid, $beforeMessageGuid, $limit);
} else {
    list($messages, $error) = getGroupMessagesByGuid($groupGuid, $limit, $offset);
}

if ($messages === false) {
    sendError($error, 500);
}

//More messages exist if we received a full batch
$hasMore = (count($messages) === $limit);
$nextCursor = null;

//Set next cursor to the oldest message GUID in this batch
if ($useCursor && $hasMore && count($messages) > 0) {
    $nextCursor = $messages[0]['message_guid'];
}

//Enhance messages with read status and profile images for current user
require_once __DIR__ . '/../../includes/db/profileImage.php';

$conn = getDbConnection();

//Get all unique sender GUIDs to batch-fetch profile images
$senderGuids = array_unique(array_column($messages, 'sender_guid'));

//Batch-fetch profile images for all senders
$profileImages = [];
if (!empty($senderGuids)) {
    $placeholders = implode(',', array_fill(0, count($senderGuids), 'uuid_to_bin(?, true)'));
    $profileSql = "SELECT bin_to_uuid(user_guid, true) as user_guid,
                          bin_to_uuid(image_guid, true) as image_guid
                   FROM profileImage
                   WHERE user_guid IN ($placeholders) AND file_path IS NOT NULL
                   GROUP BY user_guid";
    
    $profileStmt = mysqli_stmt_init($conn);
    if (mysqli_stmt_prepare($profileStmt, $profileSql)) {
        mysqli_stmt_bind_param($profileStmt, str_repeat('s', count($senderGuids)), ...$senderGuids);
        mysqli_stmt_execute($profileStmt);
        $profileResult = mysqli_stmt_get_result($profileStmt);
        
        while ($row = mysqli_fetch_assoc($profileResult)) {
            $guidHex = str_replace('-', '', $row['image_guid']);
            $profileImages[$row['user_guid']] = 'download.php?guid=' . urlencode($guidHex) . '&type=profile';
        }
        mysqli_stmt_close($profileStmt);
    }
}

//Batch-fetch read status for all messages
$messageGuids = array_column($messages, 'message_guid');
$readMessages = [];
if (!empty($messageGuids)) {
    $placeholders = implode(',', array_fill(0, count($messageGuids), 'uuid_to_bin(?, true)'));
    $readSql = "SELECT bin_to_uuid(message_guid, true) as message_guid FROM group_message_reads 
                WHERE message_guid IN ($placeholders) 
                AND user_guid = uuid_to_bin(?, true)";
    
    $readStmt = mysqli_stmt_init($conn);
    if (mysqli_stmt_prepare($readStmt, $readSql)) {
        $params = array_merge($messageGuids, [$user_guid]);
        $types = str_repeat('s', count($messageGuids)) . 's';
        mysqli_stmt_bind_param($readStmt, $types, ...$params);
        mysqli_stmt_execute($readStmt);
        $readResult = mysqli_stmt_get_result($readStmt);
        
        while ($row = mysqli_fetch_assoc($readResult)) {
            $readMessages[$row['message_guid']] = true;
        }
        mysqli_stmt_close($readStmt);
    }
}

$enhancedMessages = [];
$isEncrypted = $GLOBALS['isEncrypted'] ?? false;

foreach ($messages as $message) {
    //Check if message is read (from batch-fetched data)
    $isRead = isset($readMessages[$message['message_guid']]);

    //Get profile image URL (from batch-fetched data)
    $profileImageUrl = $profileImages[$message['sender_guid']] ?? 'img/profiledefault.jpg';

    //Decrypt message content if encryption is enabled
    $messageContent = $message['message_content'];
    if ($isEncrypted && $messageContent) {
        try {
            $messageContent = decrypt($messageContent);
        } catch (Exception $e) {
            $messageContent = '[Encrypted message]';
        }
    }

    //Build enhanced message with attachment info and profile image
    $enhancedMessage = [
        'message_guid' => $message['message_guid'],
        'sender_guid' => $message['sender_guid'],
        'sender_name' => $message['sender_username'] ?? 'Unknown User',
        'sender_profile_url' => $profileImageUrl,
        'message_content' => $messageContent,
        'sent_at' => $message['sent_at'],
        'is_read' => $isRead
    ];
    
    //Add attachment information if present
    if (!empty($message['attachment_guid'])) {
        $enhancedMessage['attachment_guid'] = $message['attachment_guid'];
        $enhancedMessage['filename'] = $message['filename'];
        $enhancedMessage['mime_type'] = $message['mime_type'];
        $enhancedMessage['url'] = $message['url'];
    }
    
    $enhancedMessages[] = $enhancedMessage;
}

//Build response data
$responseData = [
    'messages' => $enhancedMessages,
    'has_more' => $hasMore
];

//Add cursor information for cursor-based pagination
if ($useCursor && $nextCursor !== null) {
    $responseData['next_cursor'] = $nextCursor;
}

//Return success response
sendResponse(true, $responseData, "", 200);