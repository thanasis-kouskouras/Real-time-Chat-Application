<?php

include(dirname(__FILE__) . '/dbh.inc.php');

function getOrCreateConversation($fromId, $toId): array 
{
    $conn = $GLOBALS['_mc'];
    
    // Ensure consistent ordering (smaller ID first for uniqueness)
    if ($fromId > $toId) {
        $temp = $fromId;
        $fromId = $toId;
        $toId = $temp;
        $wasSwapped = true;
    } else {
        $wasSwapped = false;
    }
    
    // Check if conversation exists
    $sql = "SELECT * FROM conversation WHERE convFromId = ? AND convToId = ?";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [null, "Failed to prepare statement"];
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $fromId, $toId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $conversation = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($conversation) {
        return [$conversation, ""];
    }
    
    // Create new conversation
    $currentDate = date("Y-m-d H:i:s");
    $areFriends = areUsersFriends($wasSwapped ? $toId : $fromId, $wasSwapped ? $fromId : $toId);
    $isFromStranger = !$areFriends;
    $status = $areFriends ? 2 : 1; // 2 = Accepted, 1 = Pending
    
    $sql = "INSERT INTO conversation (convFromId, convToId, convStatus, convIsFromStranger, convCreatedDate, convUpdatedDate) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [null, "Failed to prepare statement"];
    }
    
    $strangerBit = $isFromStranger ? 1 : 0;
    mysqli_stmt_bind_param($stmt, "iiiiss", $fromId, $toId, $status, $strangerBit, $currentDate, $currentDate);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [null, "Failed to create conversation"];
    }
    
    $conversationId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    return [['ConversationID' => $conversationId, 'convFromId' => $fromId, 'convToId' => $toId, 'convStatus' => $status], ""];
}

function getUserConversations($userId): array 
{
    $conn = $GLOBALS['_mc'];
    
    $sql = "SELECT c.*, 
                   cs.status as statusName,
                   CASE 
                       WHEN c.convFromId = ? THEN u2.usersUsername 
                       ELSE u1.usersUsername 
                   END as otherUsername,
                   CASE 
                       WHEN c.convFromId = ? THEN c.convToId 
                       ELSE c.convFromId 
                   END as otherUserId,
                   CASE 
                       WHEN c.convFromId = ? THEN bin_to_uuid(u2.usersGuid, true)
                       ELSE bin_to_uuid(u1.usersGuid, true)
                   END as otherUserGuid,
                   cb.chatMessage as lastMessage,
                   cb.chatCreatedDate as lastMessageDate,
                   cb.chatFromId as lastMessageFromId,
                   (SELECT COUNT(*) FROM chatbox cb2 
                    WHERE cb2.chatConversationId = c.ConversationID 
                    AND cb2.chatToId = ? AND cb2.chatStatus < 2) as unreadCount,
                   c.convIsFromStranger
            FROM conversation c
            LEFT JOIN users u1 ON c.convFromId = u1.UsersID
            LEFT JOIN users u2 ON c.convToId = u2.UsersID
            LEFT JOIN conversationStatus cs ON c.convStatus = cs.Id
            LEFT JOIN chatbox cb ON c.convLastMessageId = cb.ChatboxID
            WHERE (c.convFromId = ? OR c.convToId = ?) 
            AND c.convFromId != c.convToId
            AND u1.usersAreDeleted != 1 AND u2.usersAreDeleted != 1
            ORDER BY COALESCE(c.convUpdatedDate, c.convCreatedDate) DESC";
    
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [[], "Failed to prepare statement"];
    }
    
    mysqli_stmt_bind_param($stmt, "iiiiii", $userId, $userId, $userId, $userId, $userId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $conversations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Convert bit field to boolean
        $row['convIsFromStranger'] = (bool)$row['convIsFromStranger'];
        
        // Double-check: exclude conversations with ourselves
        if ($row['otherUserId'] != $userId) {
            $conversations[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
    return [$conversations, ""];
}

function acceptConversation($conversationId, $userId): array 
{
    $conn = $GLOBALS['_mc'];
    $currentDate = date("Y-m-d H:i:s");
    
    // Verify user is part of this conversation
    $sql = "SELECT * FROM conversation WHERE ConversationID = ? AND (convFromId = ? OR convToId = ?)";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to prepare statement"];
    }
    
    mysqli_stmt_bind_param($stmt, "iii", $conversationId, $userId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $conversation = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$conversation) {
        return [false, "Conversation not found"];
    }
    
    // Update conversation status to Accepted (2)
    $sql = "UPDATE conversation SET convStatus = 2, convUpdatedDate = ? WHERE ConversationID = ?";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to prepare statement"];
    }
    
    mysqli_stmt_bind_param($stmt, "si", $currentDate, $conversationId);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to accept conversation"];
    }
    
    mysqli_stmt_close($stmt);
    return [true, ""];
}

function blockConversation($conversationId, $userId): array 
{
    $conn = $GLOBALS['_mc'];
    $currentDate = date("Y-m-d H:i:s");
    
    // Update conversation status to Blocked (3)
    $sql = "UPDATE conversation SET convStatus = 3, convUpdatedDate = ? 
            WHERE ConversationID = ? AND (convFromId = ? OR convToId = ?)";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to prepare statement"];
    }
    
    mysqli_stmt_bind_param($stmt, "siii", $currentDate, $conversationId, $userId, $userId);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to block conversation"];
    }
    
    mysqli_stmt_close($stmt);
    return [true, ""];
}

function updateConversationLastMessage($conversationId, $messageId): array 
{
    $conn = $GLOBALS['_mc'];
    $currentDate = date("Y-m-d H:i:s");
    
    $sql = "UPDATE conversation SET convLastMessageId = ?, convUpdatedDate = ? WHERE ConversationID = ?";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to prepare statement"];
    }
    
    mysqli_stmt_bind_param($stmt, "isi", $messageId, $currentDate, $conversationId);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [false, "Failed to update conversation"];
    }
    
    mysqli_stmt_close($stmt);
    return [true, ""];
}

function areUsersFriends($user1Id, $user2Id): bool 
{
    $conn = $GLOBALS['_mc'];
    
    $sql = "SELECT COUNT(*) as count FROM addrequest 
            WHERE ((rqstFromId = ? AND rqstToId = ?) OR (rqstFromId = ? AND rqstToId = ?)) 
            AND rqstStatus = 'Confirm'";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "iiii", $user1Id, $user2Id, $user2Id, $user1Id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $row['count'] > 0;
}

function canReceiveStrangerMessages($userId): bool 
{
    $conn = $GLOBALS['_mc'];
    
    $sql = "SELECT setting_value FROM user_settings 
            WHERE user_id = ? AND setting_name = 'accept_messages_from_strangers'";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return true; // Default to accepting
    }
    
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $setting = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    return $setting ? (bool)$setting['setting_value'] : true;
}

function getImgHtml(array $fromProfileImg, mixed $rqstFromId): string
{
    $imgHtml = "<img id='profileImage' src='img/profiledefault.jpg' >";
    if ($fromProfileImg['imgType'] == "jpg") {
        if ($fromProfileImg['imgStatus'] == 0) {
            $imgHtml = "<img id='profileImage' src='uploads/profile" . $rqstFromId . "." . $fromProfileImg['imgType'] . "?" . mt_rand() . "'>";
        }
    } else {
        $imgHtml = "<img id='profileImage' src='uploads/profile" . $rqstFromId . "." . $fromProfileImg['imgType'] . "?" . mt_rand() . "'>";
    }
    return $imgHtml;
}