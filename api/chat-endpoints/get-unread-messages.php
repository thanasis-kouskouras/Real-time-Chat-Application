<?php
/* GET UNREAD MESSAGES ENDPOINT

GET /api/chat-api.php?action=get-unread-messages
  
Returns unread direct messages with preview. */

require_once __DIR__ . '/../../includes/db/chatbox.php';
require_once __DIR__ . '/../../includes/db/profileImage.php';
require_once __DIR__ . '/../../includes/encryption.php';
require_once __DIR__ . '/../api-response.php';

//Use $user_guid provided by the chat-api.php router
$currentUserGuid = $user_guid;

//Rate limit (30 requests per minute per user)
checkRateLimit($user_guid, 'get_unread_messages', 30, 60);

//Get unread messages
list($unreadChats, $error) = getUnReadChatByGuid($currentUserGuid);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($unreadChats)) {
    sendError('Failed to retrieve unread messages', 500);
}

$isEncrypted = $GLOBALS['isEncrypted'] ?? false;

//Format message data with user details and preview
$formattedMessages = array_map(function($chat) use ($currentUserGuid, $isEncrypted) {
    $fromUserGuid = $chat['fromGuid'] ?? null;
    
    if (!$fromUserGuid) {
        app_log("Warning: Missing fromGuid in chat data");
        return null;
    }
    
    $total = $chat['total'];
    
    //Get last message for preview 
    list($lastMessage, $msgError) = getLastChatByGuid($fromUserGuid, $currentUserGuid);
    
    $messagePreview = '';

    if ($lastMessage && !$msgError) {
        $messageText = $lastMessage['chat_message'] ?? '';

        //Decrypt if encrypted
        if ($isEncrypted && $messageText) {
            try {
                $messageText = decrypt($messageText);
            } catch (Exception $e) {
                $messageText = '[Encrypted message]';
            }
        }

        //Check for attachment
        if (isset($lastMessage['url']) && $lastMessage['url'] !== null) {
            $messagePreview = 'Attachment';
        } else {
            //Truncate message for preview
            $messagePreview = mb_substr($messageText, 0, 50);
            if (mb_strlen($messageText) > 50) {
                $messagePreview .= '...';
            }
        }
    }

    //Get profile image URL using helper function
    $profileImageUrl = getProfileImageUrlByGuid($fromUserGuid);

    return [
        'fromUserGuid'    => $fromUserGuid,
        'fromUsername'    => $chat['fromName'],
        'unreadCount'     => $total,
        'messagePreview'  => $messagePreview,
        'profileImageUrl' => $profileImageUrl,
        'lastMessageTime' => $lastMessage ? ($lastMessage['chat_created_date'] ?? null) : null,
    ];
}, $unreadChats);

//Filter out null entries (in case of missing fromGuid)
$formattedMessages = array_filter($formattedMessages, function($msg) {
    return $msg !== null;
});

//Success
sendResponse(true, ['messages' => $formattedMessages], 'Unread messages retrieved successfully', 200);