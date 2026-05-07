<?php

require_once(dirname(__FILE__) . '/../dbh.inc.php');

function createMessagebyGuid($from, $to, $msg, $datetime = null, $attachmentId = null): array
{
    $error = "Failed to create message. Please try again.";
    $conn = getDbConnection();
    $isEncrypted = $GLOBALS['isEncrypted'] ?? false;
    
    $message = nl2br($msg);
    $tmpMessage = $message;
    if ($isEncrypted) {
        $tmpMessage = encrypt($message);
    }
    
    //Set initial status to 0 (Undelivered) for new messages
    $sql = "INSERT INTO chatbox (chat_from_guid, chat_to_guid, message_guid, 
                                chat_message, chat_created_date, chat_updated_date, chat_attachment_guid, chat_status) 
            VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), uuid_to_bin(uuid(), true), 
                    ?, STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), 
                    " . ($attachmentId ? "uuid_to_bin(?, true)" : "NULL") . ", 0);";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        if ($attachmentId) {
            mysqli_stmt_bind_param($stmt, "ssssss", $from, $to, $tmpMessage, $datetime, $datetime, $attachmentId);
        } else {
            mysqli_stmt_bind_param($stmt, "sssss", $from, $to, $tmpMessage, $datetime, $datetime);
        }
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        
        //Get the message GUID that was just created
        $messageGuidSql = "SELECT bin_to_uuid(message_guid, true) as message_guid FROM chatbox 
                          WHERE chat_from_guid = uuid_to_bin(?, true) 
                          AND chat_to_guid = uuid_to_bin(?, true) 
                          AND chat_message = ? 
                          ORDER BY chat_created_date DESC LIMIT 1";
        $guidStmt = mysqli_stmt_init($conn);
        if (mysqli_stmt_prepare($guidStmt, $messageGuidSql)) {
            mysqli_stmt_bind_param($guidStmt, "sss", $from, $to, $tmpMessage);
            mysqli_stmt_execute($guidStmt);
            $guidResult = mysqli_stmt_get_result($guidStmt);
            $guidRow = mysqli_fetch_assoc($guidResult);
            mysqli_stmt_close($guidStmt);
        }
        
        mysqli_stmt_close($stmt);
        return array($guidRow['message_guid'], "");
    }
}


function deliverMessage($id): array
{
    $conn = getDbConnection();
    $error = "Failed to create message. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    
    $sql = "Update chatbox Set chat_status = ?, 
            chat_updated_date = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')  
                   where message_guid = uuid_to_bin(?, true);";

    $stmt = mysqli_stmt_init($conn);
    $bit = 1;
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $bit, $currentDate, $id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        mysqli_stmt_close($stmt);
    }
    return array(true, "");
}

function syncFullChat($lastMessageDeliveredDate = null)
{
    $conn = getDbConnection();
    $error = "Failed to read all messages. Please try again.";
    $sql = "SELECT * FROM chatbox where chat_updated_date >= ?;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s",
        $lastMessageDeliveredDate);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function readChatByGuid($fromGuid, $toGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to read messages. Please try again.";
    
    //Verify friendship exists in both directions
    $friendshipSql = "SELECT 1 FROM addrequest 
                      WHERE ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)) 
                          OR (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))
                      AND request_status = 'Confirm'";
    
    $friendshipStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($friendshipStmt, $friendshipSql)) {
        mysqli_stmt_close($friendshipStmt);
        return array(false, "Failed to verify friendship");
    }
    
    mysqli_stmt_bind_param($friendshipStmt, "ssss", $fromGuid, $toGuid, $toGuid, $fromGuid);
    mysqli_stmt_execute($friendshipStmt);
    $friendshipResult = mysqli_stmt_get_result($friendshipStmt);
    $isFriend = mysqli_num_rows($friendshipResult) > 0;
    mysqli_stmt_close($friendshipStmt);
    
    if (!$isFriend) {
        return array(false, "Not friends with this user");
    }
    
    // Mark all unread messages as read (status 2)
    $updateSql = "UPDATE chatbox 
                  SET chat_status = 2,
                      chat_updated_date = NOW()
                  WHERE chat_to_guid = uuid_to_bin(?, true)
                    AND chat_from_guid = uuid_to_bin(?, true)
                    AND chat_status IN (0, 1)";
    
    $updateStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($updateStmt, $updateSql)) {
        mysqli_stmt_close($updateStmt);
        return array(false, "Failed to prepare update statement");
    }
    
    mysqli_stmt_bind_param($updateStmt, "ss", $fromGuid, $toGuid);
    if (!mysqli_stmt_execute($updateStmt)) {
        mysqli_stmt_close($updateStmt);
        return array(false, "Failed to mark messages as read");
    }
    
    mysqli_stmt_close($updateStmt);

    //Then read and return the messages
    $sql = "SELECT bin_to_uuid(cb.message_guid, true) as message_guid,
                   bin_to_uuid(cb.chat_from_guid, true) as chat_from_guid,
                   bin_to_uuid(cb.chat_to_guid, true) as chat_to_guid,
                   cb.chat_message, cb.chat_created_date, cb.chat_updated_date,
                   bin_to_uuid(cb.chat_attachment_guid, true) as chat_attachment_guid, cb.chat_is_deleted, cb.chat_status,
                   u_from.user_username as fromUsername,
                   u_to.user_username as toUsername
            FROM chatbox cb
            LEFT JOIN users u_from ON cb.chat_from_guid = u_from.user_guid
            LEFT JOIN users u_to ON cb.chat_to_guid = u_to.user_guid
            WHERE ((cb.chat_from_guid = uuid_to_bin(?, true) AND cb.chat_to_guid = uuid_to_bin(?, true))
                   OR (cb.chat_from_guid = uuid_to_bin(?, true) AND cb.chat_to_guid = uuid_to_bin(?, true)))
                  AND u_from.user_is_deleted != 1 
                  AND u_to.user_is_deleted != 1
            ORDER BY cb.chat_created_date ASC";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "ssss", $fromGuid, $toGuid, $toGuid, $fromGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getUnReadChatByGuid($toGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to get unread messages. Please try again.";
    
    /* Count messages with status 0 (Undelivered) or 1 (Delivered but not read).
    Check friend relationship in both directions. */
    $sql = "SELECT bin_to_uuid(cb.chat_from_guid, true) as fromGuid, 
                   u.user_username as fromName, 
                   COUNT(cb.message_guid) as total
            FROM chatbox cb
            JOIN users u ON cb.chat_from_guid = u.user_guid
            WHERE cb.chat_to_guid = uuid_to_bin(?, true)
              AND cb.chat_status IN (0, 1)
              AND u.user_is_deleted != 1
              AND u.user_banned != 1
              AND EXISTS (
                  SELECT 1 FROM addrequest ar
                  WHERE ar.request_status = 'Confirm'
                  AND ar.request_notification_status = 'Yes'
                  AND (
                      (ar.request_from_guid = cb.chat_from_guid AND ar.request_to_guid = cb.chat_to_guid)
                      OR
                      (ar.request_from_guid = cb.chat_to_guid AND ar.request_to_guid = cb.chat_from_guid)
                  )
              )
            GROUP BY cb.chat_from_guid, u.user_username";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $toGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}


function getLastChatByGuid($fromGuid, $toGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to get last chat. Please try again.";
    
    $sql = "SELECT bin_to_uuid(cb.message_guid, true) as message_guid,
                   bin_to_uuid(cb.chat_from_guid, true) as chat_from_guid,
                   bin_to_uuid(cb.chat_to_guid, true) as chat_to_guid,
                   cb.chat_message, cb.chat_created_date, cb.chat_updated_date,
                   bin_to_uuid(cb.chat_attachment_guid, true) as chat_attachment_guid, cb.chat_is_deleted, cb.chat_status
            FROM chatbox cb
            WHERE ((cb.chat_from_guid = uuid_to_bin(?, true) AND cb.chat_to_guid = uuid_to_bin(?, true))
                   OR (cb.chat_from_guid = uuid_to_bin(?, true) AND cb.chat_to_guid = uuid_to_bin(?, true)))
            ORDER BY cb.chat_created_date DESC
            LIMIT 1";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "ssss", $fromGuid, $toGuid, $toGuid, $fromGuid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function deleteMessageByGuid($messageGuid): array
{
    $isEncrypted = $GLOBALS['isEncrypted'];
    $deletedMsg = "<i>Deleted Message</i>";

    $tmpMessage = $deletedMsg;
    if ($isEncrypted) {
        $tmpMessage = encrypt($tmpMessage);
    }

    $currentDate = date("d/m/Y H:i:s");
    $conn = getDbConnection();
    $error = "Failed to delete message. Please try again.";
    
    //Clear chat_attachment_guid so deleted messages don't show attachments
    $sql = "UPDATE chatbox SET chat_message = ?, 
            chat_updated_date = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), 
            chat_is_deleted = ?,
            chat_attachment_guid = NULL
            WHERE message_guid = uuid_to_bin(?, true);";
    $stmt = mysqli_stmt_init($conn);
    $bit = 1;
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "ssis", $tmpMessage, $currentDate, $bit, $messageGuid);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        mysqli_stmt_close($stmt);
    }
    return array(true, "");
}

/* Get messages with cursor-based pagination for one-on-one chats.
Returns most recent messages first if no cursor, or older messages before cursor. */
function getMessagesCursorByGuid(string $fromGuid, string $toGuid, int $limit = 50, ?string $beforeMessageGuid = null): array
{
    $conn = getDbConnection();
    $error = "Failed to retrieve messages. Please try again.";

    //Validate limit
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }

    //Base path for attachments
    $basePath = 'download.php?guid=';

    if ($beforeMessageGuid === null) {
        //Initial load (get most recent messages)
        $sql = "SELECT bin_to_uuid(c.message_guid, true) as message_guid,
                       bin_to_uuid(c.chat_from_guid, true) as from_guid,
                       bin_to_uuid(c.chat_to_guid, true) as to_guid,
                       c.chat_message as message_content,
                       c.chat_created_date as created_at,
                       c.chat_status as status,
                       c.chat_is_deleted as is_deleted,
                       bin_to_uuid(c.chat_attachment_guid, true) as attachment_guid,
                       IF(c.chat_attachment_guid IS NULL, NULL, CONCAT(?, REPLACE(BIN_TO_UUID(a.guid, true), '-', ''))) as url,
                       a.mime_type,
                       a.name as filename,
                       sender.user_username as sender_username,
                       bin_to_uuid(sender.user_guid, true) as sender_guid,
                       bin_to_uuid(pi.image_guid, true) as sender_image_guid,
                       pi.file_path as sender_image_path
                FROM chatbox c
                LEFT JOIN attachments a ON c.chat_attachment_guid = a.guid
                LEFT JOIN users sender ON c.chat_from_guid = sender.user_guid
                LEFT JOIN profileImage pi ON sender.user_guid = pi.user_guid AND pi.file_path IS NOT NULL
                WHERE ((c.chat_from_guid = uuid_to_bin(?, true) AND c.chat_to_guid = uuid_to_bin(?, true))
                    OR (c.chat_from_guid = uuid_to_bin(?, true) AND c.chat_to_guid = uuid_to_bin(?, true)))
                ORDER BY c.chat_created_date DESC
                LIMIT ?";

        $stmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, false, null, $error);
        }

        mysqli_stmt_bind_param($stmt, "sssssi", $basePath, $fromGuid, $toGuid, $toGuid, $fromGuid, $limit);
    } else {
        //Load older messages (get messages before the cursor)
        $sql = "SELECT bin_to_uuid(c.message_guid, true) as message_guid,
                       bin_to_uuid(c.chat_from_guid, true) as from_guid,
                       bin_to_uuid(c.chat_to_guid, true) as to_guid,
                       c.chat_message as message_content,
                       c.chat_created_date as created_at,
                       c.chat_status as status,
                       c.chat_is_deleted as is_deleted,
                       bin_to_uuid(c.chat_attachment_guid, true) as attachment_guid,
                       IF(c.chat_attachment_guid IS NULL, NULL, CONCAT(?, REPLACE(BIN_TO_UUID(a.guid, true), '-', ''))) as url,
                       a.mime_type,
                       a.name as filename,
                       sender.user_username as sender_username,
                       bin_to_uuid(sender.user_guid, true) as sender_guid,
                       bin_to_uuid(pi.image_guid, true) as sender_image_guid,
                       pi.file_path as sender_image_path
                FROM chatbox c
                LEFT JOIN attachments a ON c.chat_attachment_guid = a.guid
                LEFT JOIN users sender ON c.chat_from_guid = sender.user_guid
                LEFT JOIN profileImage pi ON sender.user_guid = pi.user_guid AND pi.file_path IS NOT NULL
                WHERE ((c.chat_from_guid = uuid_to_bin(?, true) AND c.chat_to_guid = uuid_to_bin(?, true))
                    OR (c.chat_from_guid = uuid_to_bin(?, true) AND c.chat_to_guid = uuid_to_bin(?, true)))
                  AND c.chat_created_date < (SELECT chat_created_date FROM chatbox WHERE message_guid = uuid_to_bin(?, true))
                ORDER BY c.chat_created_date DESC
                LIMIT ?";

        $stmt = mysqli_stmt_init($conn);
        if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, false, null, $error);
        }

        mysqli_stmt_bind_param($stmt, "ssssssi", $basePath, $fromGuid, $toGuid, $toGuid, $fromGuid, $beforeMessageGuid, $limit);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, false, null, $error);
    }

    $resultData = mysqli_stmt_get_result($stmt);
    $messages = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    //Determine if there are more messages
    $hasMore = (count($messages) === $limit);

    //Get next cursor (oldest message in this batch)
    $nextCursor = null;
    if ($hasMore && count($messages) > 0) {
        $nextCursor = $messages[count($messages) - 1]['message_guid'];
    }

    //Reverse to chronological order for display (oldest first)
    $messages = array_reverse($messages);

    return array($messages, $hasMore, $nextCursor, "");
}

/* Mark all messages as read between two users.
Used when friendship is deleted to prevent unread indicators from reappearing. */
function markAllMessagesReadBetweenUsersByGuid(string $userGuid1, string $userGuid2): array
{
    $conn = getDbConnection();
    $error = "Failed to mark messages as read. Please try again.";

    //Mark all messages as read (status 2) in both directions
    $sql = "UPDATE chatbox
            SET chat_status = 2,
                chat_updated_date = NOW()
            WHERE chat_status IN (0, 1)
              AND (
                  (chat_from_guid = uuid_to_bin(?, true) AND chat_to_guid = uuid_to_bin(?, true))
                  OR
                  (chat_from_guid = uuid_to_bin(?, true) AND chat_to_guid = uuid_to_bin(?, true))
              )";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    mysqli_stmt_bind_param($stmt, "ssss", $userGuid1, $userGuid2, $userGuid2, $userGuid1);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }

    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return array(true, "Marked $affectedRows messages as read");
}