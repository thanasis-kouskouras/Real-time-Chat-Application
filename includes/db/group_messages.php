<?php
require_once(dirname(__FILE__) . '/../dbh.inc.php');
require_once(dirname(__FILE__) . '/../encryption.php');
require_once(dirname(__FILE__) . '/group_chats.php');
require_once(dirname(__FILE__) . '/group_members.php');


//Send a message to a group using GUIDs
function sendGroupMessageByGuid($groupGuid, $user_guid, $content, $attachmentId = null): array
{
    $conn = getDbConnection();
    $error = "Failed to send group message. Please try again.";
    $isEncrypted = $GLOBALS['isEncrypted'] ?? false;

    //Validate message content length (allow empty if there's an attachment)
    if (strlen($content) === 0 && $attachmentId === null) {
        return array(false, "Message content cannot be empty.");
    }
    if (strlen($content) > 5000) {
        return array(false, "Message content cannot exceed 5000 characters.");
    }

    //Check if sender is a member of the group
    if (!isGroupMemberByGuid($groupGuid, $user_guid)) {
        return array(false, "You are not a member of this group.");
    }

    //Check if the group is active (cannot send messages to deactivated groups)
    if (!isGroupChatActiveByGuid($groupGuid)) {
        return array(false, "Cannot send messages to a deactivated group. The group needs at least 3 members to be active.");
    }

    //Encrypt message content if encryption is enabled
    $messageContent = nl2br($content);
    $tmpMessage = $messageContent;
    if ($isEncrypted) {
        $tmpMessage = encrypt($messageContent);
    }

    //Generate a new message GUID
    $messageGuid = generateGuid();

    //Insert the message
    if ($attachmentId !== null) {
        $sql = "INSERT INTO group_messages (message_guid, group_guid, sender_guid, message_content, attachment_guid, sent_at)
                VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), uuid_to_bin(?, true), ?, uuid_to_bin(?, true), NOW())";
        $stmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        mysqli_stmt_bind_param($stmt, "sssss", $messageGuid, $groupGuid, $user_guid, $tmpMessage, $attachmentId);
    } else {
        $sql = "INSERT INTO group_messages (message_guid, group_guid, sender_guid, message_content, sent_at)
                VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), uuid_to_bin(?, true), ?, NOW())";
        $stmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        mysqli_stmt_bind_param($stmt, "ssss", $messageGuid, $groupGuid, $user_guid, $tmpMessage);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_close($stmt);
    
    //Update group activity timestamp and message count
    $updateSql = "UPDATE group_chats 
                  SET last_activity = NOW(), message_count = message_count + 1 
                  WHERE group_guid = uuid_to_bin(?, true)";
    $updateStmt = mysqli_stmt_init($conn);
    if (mysqli_stmt_prepare($updateStmt, $updateSql)) {
        mysqli_stmt_bind_param($updateStmt, "s", $groupGuid);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }
    
    //Increment unread count for all members except sender
    $unreadSql = "UPDATE group_members 
                  SET unread_count = unread_count + 1 
                  WHERE group_guid = uuid_to_bin(?, true) AND user_guid != uuid_to_bin(?, true)";
    $unreadStmt = mysqli_stmt_init($conn);
    if (mysqli_stmt_prepare($unreadStmt, $unreadSql)) {
        mysqli_stmt_bind_param($unreadStmt, "ss", $groupGuid, $user_guid);
        mysqli_stmt_execute($unreadStmt);
        mysqli_stmt_close($unreadStmt);
    }
    
    //Get the sent message data
    list($message, $getError) = getMessageByGuidOnly($messageGuid);
    if (!$message) {
        return array(false, $getError);
    }

    return array($message, "");
}

//Get messages for a group with pagination
function getGroupMessagesByGuid($groupGuid, $limit = 50, $offset = 0): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve group messages. Please try again.";
    
    //Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }
    
    //Get base path for file URLs
    $basePath = 'download.php?guid=';
    
    $sql = "SELECT bin_to_uuid(gm.message_guid, true) as message_guid, bin_to_uuid(gm.group_guid, true) as group_guid,
                   bin_to_uuid(gm.sender_guid, true) as sender_guid, gm.message_content,
                   gm.sent_at, bin_to_uuid(gm.attachment_guid, true) as attachment_guid,
                   u.user_username as sender_username,
                   a.name as filename,
                   a.mime_type,
                   IF(gm.attachment_guid IS NULL, NULL, CONCAT(?, BIN_TO_UUID(a.guid, true), '&type=attachment')) as url
            FROM group_messages gm
            LEFT JOIN users u ON gm.sender_guid = u.user_guid
            LEFT JOIN attachments a ON gm.attachment_guid = a.guid
            WHERE gm.group_guid = uuid_to_bin(?, true)
            ORDER BY gm.sent_at DESC
            LIMIT ? OFFSET ?";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "ssii", $basePath, $groupGuid, $limit, $offset);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    //Reverse the array to show oldest first
    $rows = array_reverse($rows);
    
    return array($rows, "");
}

//Get messages for a group with cursor-based pagination
function getGroupMessagesCursorByGuid($groupGuid, $beforeMessageId = null, $limit = 50): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve group messages. Please try again.";
    
    //Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }
    
    //Get base path for file URLs
    $basePath = 'download.php?guid=';
    
    if ($beforeMessageId === null) {
        //Initial load (get most recent messages)
        $sql = "SELECT bin_to_uuid(gm.message_guid, true) as message_guid, bin_to_uuid(gm.group_guid, true) as group_guid,
                       bin_to_uuid(gm.sender_guid, true) as sender_guid, gm.message_content,
                       gm.sent_at, bin_to_uuid(gm.attachment_guid, true) as attachment_guid,
                       u.user_username as sender_username,
                       a.name as filename,
                       a.mime_type,
                       IF(gm.attachment_guid IS NULL, NULL, CONCAT(?, BIN_TO_UUID(a.guid, true), '&type=attachment')) as url
                FROM group_messages gm
                LEFT JOIN users u ON gm.sender_guid = u.user_guid
                LEFT JOIN attachments a ON gm.attachment_guid = a.guid
                WHERE gm.group_guid = uuid_to_bin(?, true)
                ORDER BY gm.sent_at DESC
                LIMIT ?";
        $stmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        
        mysqli_stmt_bind_param($stmt, "ssi", $basePath, $groupGuid, $limit);
    } else {
        //Load older messages (get messages before the cursor)
        $sql = "SELECT bin_to_uuid(gm.message_guid, true) as message_guid, bin_to_uuid(gm.group_guid, true) as group_guid,
                       bin_to_uuid(gm.sender_guid, true) as sender_guid, gm.message_content,
                       gm.sent_at, bin_to_uuid(gm.attachment_guid, true) as attachment_guid,
                       u.user_username as sender_username,
                       a.name as filename,
                       a.mime_type,
                       IF(gm.attachment_guid IS NULL, NULL, CONCAT(?, BIN_TO_UUID(a.guid, true), '&type=attachment')) as url
                FROM group_messages gm
                LEFT JOIN users u ON gm.sender_guid = u.user_guid
                LEFT JOIN attachments a ON gm.attachment_guid = a.guid
                WHERE gm.group_guid = uuid_to_bin(?, true) AND gm.sent_at < (SELECT sent_at FROM group_messages WHERE message_guid = uuid_to_bin(?, true))
                ORDER BY gm.sent_at DESC
                LIMIT ?";
        $stmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        
        mysqli_stmt_bind_param($stmt, "sssi", $basePath, $groupGuid, $beforeMessageId, $limit);
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    
    //Reverse the array to show oldest first
    $rows = array_reverse($rows);
    
    return array($rows, "");
}

//Get a message
function getMessageByGuidOnly($messageGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve message. Please try again.";
    
    $sql = "SELECT bin_to_uuid(gm.message_guid, true) as message_guid, bin_to_uuid(gm.group_guid, true) as group_guid,
                   bin_to_uuid(gm.sender_guid, true) as sender_guid, gm.message_content,
                   gm.sent_at,
                   u.user_username as sender_username
            FROM group_messages gm
            LEFT JOIN users u ON gm.sender_guid = u.user_guid
            WHERE gm.message_guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "s", $messageGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    
    if (!$row) {
        return array(false, "Message not found.");
    }
    
    return array($row, "");
}


//Mark all messages in a group as read for a specific user
function markAllMessagesReadByGuid($groupGuid, $user_guid): array
{
    //Validate GUID formats
    if (!isValidGuid($groupGuid) || !isValidGuid($user_guid)) {
        return [false, "Invalid GUID format"];
    }
    
    $conn = getDbConnection();
    
    try {
        //Start transaction
        mysqli_begin_transaction($conn);
        
        //Update the last_read_at timestamp and reset unread_count for this user in this group
        $stmt = $conn->prepare("
            UPDATE group_members 
            SET last_read_at = NOW(), unread_count = 0
            WHERE group_guid = uuid_to_bin(?, true) 
            AND user_guid = uuid_to_bin(?, true)
        ");
        $stmt->bind_param("ss", $groupGuid, $user_guid);
        
        if (!$stmt->execute()) {
            mysqli_rollback($conn);
            return [false, "Failed to update read status"];
        }
        
        $stmt->close();

        //Insert read records for all unread messages in group_message_reads table (This ensures the API correctly shows messages as read)
        $insertStmt = $conn->prepare("
            INSERT IGNORE INTO group_message_reads (message_guid, user_guid, read_at)
            SELECT gm.message_guid, uuid_to_bin(?, true), NOW()
            FROM group_messages gm
            WHERE gm.group_guid = uuid_to_bin(?, true)
            AND gm.is_deleted = 0
            AND NOT EXISTS (
                SELECT 1 FROM group_message_reads gmr
                WHERE gmr.message_guid = gm.message_guid
                AND gmr.user_guid = uuid_to_bin(?, true)
            )
        ");
        $insertStmt->bind_param("sss", $user_guid, $groupGuid, $user_guid);
        
        if (!$insertStmt->execute()) {
            mysqli_rollback($conn);
            return [false, "Failed to mark messages as read"];
        }
        
        $insertStmt->close();
        
        //Commit transaction
        mysqli_commit($conn);
        
        return [true, ""];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        app_log("Error in markAllMessagesReadByGuid: " . $e->getMessage());
        return [false, "Database error"];
    }
}

//Delete a group message (soft delete, sets is_deleted=1 and replaces content)
function deleteGroupMessageWithGuid($messageGuid, $user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to delete message. Please try again.";
    $isEncrypted = $GLOBALS['isEncrypted'] ?? false;

    //Get message details
    list($message, $getError) = getMessageByGuidOnly($messageGuid);
    if (!$message) {
        return array(false, $getError);
    }

    //Check if user is the sender or a group admin
    $isSender = ($message['sender_guid'] == $user_guid);
    $isAdmin = isGroupAdminByGuid($message['group_guid'], $user_guid);

    if (!$isSender && !$isAdmin) {
        return array(false, "You do not have permission to delete this message.");
    }

    /* Update message to show "Deleted Message".
    Clear attachment_guid so deleted messages don't show attachments.
    We don't touch sent_at (the bubble must keep the original send timestamp). */
    $deletedMsg = "<i>Deleted Message</i>";
    $tmpMessage = $deletedMsg;
    if ($isEncrypted) {
        $tmpMessage = encrypt($deletedMsg);
    }

    $sql = "UPDATE group_messages
            SET message_content = ?,
                is_deleted = 1,
                attachment_guid = NULL
            WHERE message_guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "ss", $tmpMessage, $messageGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_close($stmt);
    return array(true, "");
}