<?php
/* LIST GROUPS ENDPOINT
 
GET /api/group-chat-api.php?action=list
 
Retrieves all group chats where the user is a member (includes unread counts, last message preview, and member info). */

require_once __DIR__ . '/../../includes/upload/UploadController.php';

//Rate limit (max 30 group list requests per minute)
checkRateLimit($user_guid, 'list_groups', 30, 60);

function getGroupImageUrlFromDatabase($groupGuid, $uploadController) {
    $url = $uploadController->getGroupImageUrl($groupGuid);
    return empty($url) ? 'img/groupdefault.png' : $url;
}

//Only allow GET requests
if ($method !== 'GET') {
    sendError("Method not allowed. Use GET.", 405);
}

//Get user's groups
list($groups, $error) = getUserGroupChatsByGuid($user_guid);

if ($error !== "") {
    sendError($error, 500);
}

if (!is_array($groups)) {
    sendError("Failed to retrieve groups", 500);
}

//Initialize upload controller for getting image URLs
$uploadController = new UploadController(getDbConnection());

//Enhance each group with additional information
$enhancedGroups = [];

foreach ($groups as $group) {
    $groupGuid = $group['group_guid'];
    
    //Get last message preview
    list($messages) = getGroupMessagesByGuid($groupGuid, 1, 0);
    $lastMessage = null;
    $lastMessageTime = null;
    $isEncrypted = $GLOBALS['isEncrypted'] ?? false;

    if (is_array($messages) && count($messages) > 0) {
        $lastMsg = $messages[0];
        $lastMessage = $lastMsg['message_content'];
        $lastMessageTime = $lastMsg['sent_at'];

        //Decrypt message content if encryption is enabled
        if ($isEncrypted && $lastMessage) {
            try {
                $lastMessage = decrypt($lastMessage);
            } catch (Exception $e) {
                $lastMessage = '[Encrypted message]';
            }
        }

        //Truncate long messages for preview
        if (strlen($lastMessage) > 100) {
            $lastMessage = substr($lastMessage, 0, 100) . '...';
        }
    }
    
    //Build enhanced group object
    $enhancedGroups[] = [
        'group_guid' => $groupGuid,
        'group_name' => $group['group_name'],
        'group_image_url' => getGroupImageUrlFromDatabase($groupGuid, $uploadController),
        'member_count' => $group['member_count'],
        'unread_count' => $group['unread_count'],
        'last_message' => $lastMessage,
        'last_message_time' => $lastMessageTime,
        'is_admin' => ($group['role'] === 'admin'),
        'is_active' => ($group['is_active'] == 1)
    ];
}

//Groups are already sorted by last_activity DESC from getUserGroupChats()

//Return success response
sendResponse(true, ['groups' => $enhancedGroups], "", 200);