<?php
include(dirname(__FILE__) . '/../dbh.inc.php');

function updateNotification($requestStatus, $notificationStatus, $rqstFromId, $rqstToId, $RequestIDFromId): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to update notification. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    $sql = "UPDATE addrequest SET rqstStatus = ?, rqstNotificationStatus = ?,
                                        rqstUpdateTime = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')
                   WHERE RequestID = '$RequestIDFromId' AND rqstFromId = '$rqstFromId' AND rqstToId = '$rqstToId';";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "sss", $requestStatus, $notificationStatus, $currentDate);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function createNotification($rqstFromId, $rqstToId, $rqstStatus, $rqstNotificationStatus): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to create notification. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    $sql = " INSERT INTO addrequest (rqstFromId, rqstToId, rqstStatus, rqstNotificationStatus, rqstUpdateTime, rqstDatetime)
    VALUES (?, ?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'));";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ssssss", $rqstFromId,
        $rqstToId, $rqstStatus, $rqstNotificationStatus, $currentDate, $currentDate);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getPendingNotifications($id): array
{

    $conn = $GLOBALS['_mc'];
    $error = "Failed to get notification. Please try again.";
    $sql = "SELECT * FROM addrequest WHERE rqstToId = ? AND rqstStatus = 'Pending'    AND rqstNotificationStatus = 'Yes';";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getNotificationFromTo($fromId, $toId): bool|array|null
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get notification. Please try again.";
    $sql = "SELECT * FROM addrequest WHERE rqstFromId = ? AND rqstToId = ?";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ii", $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getFriendsCount($id): int|string
{
    $conn = $GLOBALS['_mc'];
    $sql = "SELECT * FROM addrequest WHERE rqstToId = '$id' AND rqstStatus = 'Confirm' AND rqstNotificationStatus = 'Yes';";
    $result = mysqli_query($conn, $sql);
    return mysqli_num_rows($result);
}

function getSyncFriends($id, $lastdate): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to Sync Friends. Please try again.";
    $sql = "select RequestID, rqstFromId, rqstToId, rqstStatus, rqstNotificationStatus, rqstDatetime, rqstUpdateTime, ub.UsersID, ub.usersUsername, ub.usersEmail,ub.usersStatus, ub.usersEmailVerified,ub.usersCreatedDate,ub.usersVerificationToken,bin_to_uuid(ub.usersguid, true) as usersGuid, ub.usersAreDeleted,ub.usersVerificationTokenExpireDate 
    FROM addrequest, users ua, users ub 
    WHERE rqstToId = ? 
    AND rqstStatus != 'Reject' 
    AND rqstNotificationStatus = 'Yes' 
    and ua.UsersID = rqstToId 
    and ua.usersAreDeleted != 1 
    and ub.UsersID = rqstFromId 
    and ub.usersAreDeleted != 1 
    and rqstUpdateTime >= ? 
    order by usersStatus desc ; ";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "is", $id, $lastdate);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getFriends($id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get Friends. Please try again.";
    $sql = "SELECT RequestID, rqstFromId, rqstToId, rqstStatus,
       		rqstNotificationStatus, rqstDatetime, rqstUpdateTime,
       		ub.UsersID, ub.usersUsername, ub.usersEmail, ub.usersStatus,
       		ub.usersEmailVerified, ub.usersCreatedDate, ub.usersVerificationToken,
       		bin_to_uuid(ub.usersguid, true) as usersGuid,
       		ub.usersAreDeleted, ub.usersVerificationTokenExpireDate 
       		FROM addrequest
       		JOIN users ua ON ua.UsersID = rqstFromId
       		JOIN users ub ON ub.UsersID = rqstFromId
       		WHERE rqstToId = ? 
       		AND rqstStatus = 'Confirm' 
       		AND rqstNotificationStatus = 'Yes'
       		AND ua.usersAreDeleted != 1
       		AND ub.usersAreDeleted != 1
       		ORDER BY ub.usersStatus DESC, ub.usersUsername ASC";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
    return array($row, "");
}


function getConfirmedFriendOnly($fromId, $toId): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get Friends. Please try again.";
    $sql = "SELECT * FROM addrequest WHERE rqstFromId = ? AND rqstToId = ?            AND rqstStatus = 'Confirm' ;";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ii", $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getConfirmedFriend($fromId, $toId): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get Friends. Please try again.";
    $sql = "SELECT * FROM addrequest WHERE rqstFromId = ? AND rqstToId = ?            AND rqstStatus = 'Confirm' AND rqstNotificationStatus = 'Yes';";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ii", $fromId, $toId);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function rqstIdExists($rqstId): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed to get Friends. Please try again.";
    $sql = "SELECT * FROM addrequest WHERE RequestID = ?;";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $rqstId);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($resultData)) {
        return array($row, "");
    } else {
        return array(false, "");
    }
}