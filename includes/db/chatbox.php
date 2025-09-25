<?php

include(dirname(__FILE__) . '/../dbh.inc.php');
function createMessagebyGuid($from, $to, $msg, $datetime = null, $attachmentId = null): array
{
    list($userFrom, $error) = getUserByGuid($from);
    if ($error != "")
        return [false, $error];
    list($userTo, $error) = getUserByGuid($to);
    if ($error != "")
        return [false, $error];
    $userFromId = $userFrom['UsersID'];
    $userToId = $userTo['UsersID'];
    return createMessage($userFromId, $userToId, $msg, $datetime, $attachmentId);

}

function createMessage($from, $to, $msg, $datetime = null, $attachmentId = null): array
{
    $isEncrypted = $GLOBALS['isEncrypted'];

    $error = "Failed to create message. Please try again.";
    $conn = $GLOBALS['_mc'];
    $message = nl2br($msg);
    $tmpMessage = $message;
    if ($isEncrypted) {
        $tmpMessage = encrypt($message);
    }
    $sql = "INSERT INTO chatbox (chatFromId, chatToId, chatMessage, chatCreatedDate,chatUpdatedDate, chatAttachmentId) VALUES (?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), ?);";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "iisssi", $from, $to, $tmpMessage, $datetime, $datetime, $attachmentId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        $insertId = $stmt->insert_id;
        mysqli_stmt_close($stmt);
    }
    return array($insertId, "");
}

function deliverMessage($id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to create message. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    $sql = "Update chatbox Set chatStatus = ?, chatUpdatedDate = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s') where ChatboxID = ?;";

    $stmt = mysqli_stmt_init($conn);
    $bit = 1;
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "isi", $bit, $currentDate, $id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        mysqli_stmt_close($stmt);
    }
    return array(true, "");
}

function deleteMessageById($id): array
{
    $isEncrypted = $GLOBALS['isEncrypted'];
    $deletedMsg = "<i>Deleted Message</i>";

    $tmpMessage = $deletedMsg;
    if ($isEncrypted) {
        $tmpMessage = encrypt($tmpMessage);
    }

    $currentDate = date("d/m/Y H:i:s");
    $conn = $GLOBALS['_mc'];
    $error = "Failed to delete message. Please try again.";
    $sql = "UPDATE chatbox SET chatMessage = '$tmpMessage', chatUpdatedDate = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), chatIsDeleted = ? WHERE ChatboxID = ?;";

    $stmt = mysqli_stmt_init($conn);
    $bit = 1;
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "sii", $currentDate, $bit, $id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        mysqli_stmt_close($stmt);
    }
    return array(false, "");
}

function readChat($fromId, $toId): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to read messages. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    $sql = "Update chatbox set chatStatus = 2, chatUpdatedDate = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s') where chatFromId = ? and chatToId = ? and chatStatus = 1";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $toId = intval($toId);
    mysqli_stmt_bind_param($stmt, "sii", $currentDate, $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function deliverChat($fromId, $toId): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to deliver messages. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    $sql = "Update chatbox set chatStatus = 1, chatUpdatedDate = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s') where chatFromId = ? and chatToId = ? and chatStatus = 0";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "sii", $currentDate, $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    return array(true, "");
}

function getLastChat($fromId, $toId)
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to read last messages. Please try again.";

    $sql = "CALL getLastUserChat(?,?)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ii", $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getChat($fromId, $toId)
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to read messages. Please try again.";

    $sql = "CALL getUserChat(?,?)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ii", $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function syncFromChat($fromId, $lastMessageDeliveredDate = null)
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to read messages. Please try again.";
    $sql = "SELECT ChatboxID, chatFromId, chatToId, chatMessage, chatCreatedDate,chatUpdatedDate, chatAttachmentId, chatIsDeleted, chatStatus, UsersID, usersUsername, usersEmail, usersStatus, usersemailverified, usersCreatedDate, 
    bin_to_uuid(usersguid, true) as usersGuid 
    FROM chatbox, users 
    WHERE (chatFromId = ? OR chatToId = ?) 
    AND chatUpdatedDate >= ? 
    and chatToId = UsersID 
    and usersAreDeleted != 1 ;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "iis",
        $fromId, $fromId, $lastMessageDeliveredDate);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function syncSpecificChat($fromId, $toId, $lastMessageDeliveredDate = null)
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to read messages. Please try again.";
    $sql = "SELECT * FROM chatbox, users WHERE ((chatFromId = ? AND chatToId = ?) 
    OR (chatFromId = ? AND chatToId = ?)) AND chatUpdatedDate >= ? AND UsersID;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "iiiis",
        $fromId, $toId, $toId, $fromId, $lastMessageDeliveredDate);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function syncFullChat($lastMessageDeliveredDate = null)
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to read all messages. Please try again.";
    $sql = "SELECT * FROM chatbox where chatUpdatedDate >= ?;";
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

function getUnDeliveredChat($toId)
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get unread messages. Please try again.";
    $sql = "SELECT chatFromId as fromId, usersUsername  as fromName, count(ChatboxID) as total
    from chatbox, users, addrequest as ar
    where chatToId = ? 
    AND chatStatus = 0
    and chatFromId = UsersID
    and ar.rqstFromId = chatFromId
    and ar.rqstToId = chatToId
    AND ar.rqstStatus = 'Confirm'
    AND ar.rqstNotificationStatus = 'Yes'
    group by chatFromId, usersUsername ";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "i",
        $toId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getUnReadChat($toId): array
{
    $bit = 0;
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get unread messages. Please try again.";
    $sql = "SELECT chatFromId as fromId, usersUsername  as fromName, count(ChatboxID) as total
    from chatbox, users, addrequest as ar
    where chatToId = ? 
    AND chatStatus = 1
    and chatFromId = UsersID
    and ar.rqstFromId = chatFromId
    and ar.rqstToId = chatToId
    AND ar.rqstStatus = 'Confirm'
    AND ar.rqstNotificationStatus = 'Yes'
    group by fromId, fromName";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "i", $toId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}