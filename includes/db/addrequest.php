<?php
require_once(dirname(__FILE__) . '/../dbh.inc.php');

function getSyncFriendsByGuid($user_guid, $lastdate): array
{
    $conn = getDbConnection();
    $error = "Failed to Sync Friends. Please try again.";
    $sql = "SELECT bin_to_uuid(ar.request_guid, true) as RequestGuid, 
                   bin_to_uuid(ar.request_from_guid, true) as request_from_guid, 
                   bin_to_uuid(ar.request_to_guid, true) as request_to_guid, 
                   ar.request_status, ar.request_notification_status, ar.request_datetime, ar.request_update_time,
                   ub.user_username, ub.user_email, ub.user_status,
                   ub.user_email_verified, ub.user_created_date, ub.user_verification_token,
                   bin_to_uuid(ub.user_guid, true) as user_guid,
                   ub.user_is_deleted, ub.user_verification_token_expire_date 
            FROM addrequest ar
            JOIN users ua ON ar.request_to_guid = ua.user_guid
            JOIN users ub ON ar.request_from_guid = ub.user_guid
            WHERE ar.request_to_guid = uuid_to_bin(?, true)
              AND ar.request_status != 'Reject' 
              AND ar.request_notification_status = 'Yes'
              AND ua.user_is_deleted != 1
              AND ub.user_is_deleted != 1
              AND ua.user_banned != 1
              AND ub.user_banned != 1
              AND ar.request_update_time >= ?
            ORDER BY ub.user_status DESC";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ss", $user_guid, $lastdate);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getFriends($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to get Friends. Please try again.";
    $sql = "SELECT bin_to_uuid(request_guid, true) as request_guid, 
       		bin_to_uuid(request_from_guid, true) as request_from_guid,
       		bin_to_uuid(request_to_guid, true) as request_to_guid,
       		request_status, request_notification_status, request_datetime, request_update_time,
       		bin_to_uuid(ub.user_guid, true) as user_guid,
       		ub.user_username, ub.user_email, ub.user_status,
       		ub.user_email_verified, ub.user_created_date, ub.user_verification_token,
       		ub.user_is_deleted, ub.user_verification_token_expire_date 
       		FROM addrequest
       		JOIN users ub ON ub.user_guid = request_from_guid
       		WHERE request_to_guid = uuid_to_bin(?, true)
       		AND request_status = 'Confirm' 
       		AND request_notification_status = 'Yes'
       		AND ub.user_is_deleted != 1
       		AND ub.user_banned != 1
       		ORDER BY ub.user_status DESC, ub.user_username ASC";

    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $user_guid);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_all($resultData, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getPendingNotificationsByGuid($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to get pending notifications. Please try again.";
    
    $sql = "SELECT ar.*, bin_to_uuid(ar.request_from_guid, true) as request_from_guid_str, 
                   bin_to_uuid(ar.request_to_guid, true) as request_to_guid_str,
                   u.user_username, u.user_email, u.user_status
            FROM addrequest ar
            JOIN users u ON ar.request_from_guid = u.user_guid
            WHERE ar.request_to_guid = uuid_to_bin(?, true) 
            AND ar.request_status = 'Pending' 
            AND ar.request_notification_status = 'Yes'
            AND u.user_is_deleted != 1
            AND u.user_banned != 1
            ORDER BY ar.request_datetime DESC;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "s", $user_guid);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($resultData)) {
        //Use the string versions selected directly
        $row['request_from_guid'] = $row['request_from_guid_str'];
        $row['request_to_guid'] = $row['request_to_guid_str'];
        //Remove the temporary string columns
        unset($row['request_from_guid_str'], $row['request_to_guid_str']);
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    return array($notifications, "");
}

function getNotificationFromToByGuid($fromGuid, $toGuid): bool|array|null
{
    $conn = getDbConnection();
    $sql = "SELECT * FROM addrequest WHERE request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ss", $fromGuid, $toGuid);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return false;
    }
    $resultData = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($resultData)) {
        //Convert binary GUIDs to string format
        if (isset($row['request_from_guid'])) {
            $row['request_from_guid'] = bin2hex($row['request_from_guid']);
        }
        if (isset($row['request_to_guid'])) {
            $row['request_to_guid'] = bin2hex($row['request_to_guid']);
        }
        mysqli_stmt_close($stmt);
        return $row;
    } else {
        mysqli_stmt_close($stmt);
        return false;
    }
}

function getFriendsByGuid($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed to get Friends. Please try again.";
    
    $sql = "SELECT DISTINCT bin_to_uuid(u.user_guid, true) as user_guid, u.user_username, u.user_email, u.user_status
            FROM addrequest ar
            JOIN users u ON (
                (ar.request_from_guid = uuid_to_bin(?, true) AND ar.request_to_guid = u.user_guid) OR
                (ar.request_to_guid = uuid_to_bin(?, true) AND ar.request_from_guid = u.user_guid)
            )
            WHERE ar.request_status = 'Confirm'
            AND u.user_is_deleted != 1
            AND u.user_banned != 1
            ORDER BY u.user_username;";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ss", $user_guid, $user_guid);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $friends = [];
    while ($row = mysqli_fetch_assoc($resultData)) {
        $friends[] = $row;
    }
    mysqli_stmt_close($stmt);
    return array($friends, "");
}

function getConfirmedFriendByGuid($fromGuid, $toGuid): array
{
    $conn = getDbConnection();
    $error = "Failed to get confirmed friend. Please try again.";
    
    $sql = "SELECT * FROM addrequest 
            WHERE ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)) OR
                   (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))
            AND request_status = 'Confirm';";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "ssss", $fromGuid, $toGuid, $toGuid, $fromGuid);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    $resultData = mysqli_stmt_get_result($stmt);
    $friends = [];
    while ($row = mysqli_fetch_assoc($resultData)) {
        //Convert binary GUIDs to string format
        if (isset($row['request_from_guid'])) {
            $row['request_from_guid'] = bin2hex($row['request_from_guid']);
        }
        if (isset($row['request_to_guid'])) {
            $row['request_to_guid'] = bin2hex($row['request_to_guid']);
        }
        $friends[] = $row;
    }
    mysqli_stmt_close($stmt);
    return array($friends, "");
}
function updateNotificationByGuid($fromGuid, $toGuid, $requestStatus, $notificationStatus): bool
{
    $conn = getDbConnection();
    $currentDate = date("d/m/Y H:i:s");
    
    $sql = "UPDATE addrequest SET request_status = ?, request_notification_status = ?,
                                  request_update_time = STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s')
            WHERE request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)";
    
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "sssss", $requestStatus, $notificationStatus, $currentDate, $fromGuid, $toGuid);
    if (!mysqli_stmt_execute($stmt)){
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    return true;
}

function addFriendByGuid($fromGuid, $toGuid, $status, $notificationStatus): bool
{
    $conn = getDbConnection();
    $error = "Failed to send friend request. Please try again.";
    $currentDate = date("d/m/Y H:i:s");
    
    //Check if request already exists and get its status
    $checkSql = "SELECT request_status FROM addrequest WHERE 
                 ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)) OR
                  (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))";
    $checkStmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($checkStmt, $checkSql)) {
        mysqli_stmt_close($checkStmt);
        return false;
    }
    mysqli_stmt_bind_param($checkStmt, "ssss", $fromGuid, $toGuid, $toGuid, $fromGuid);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $row = mysqli_fetch_assoc($checkResult);
        $existingStatus = $row['request_status'];
        mysqli_stmt_close($checkStmt);
        
        //If status is "Reject", delete the old request and allow creating a new one
        if ($existingStatus === 'Reject') {
            $deleteSql = "DELETE FROM addrequest WHERE 
                         ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)) OR
                          (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))";
            $deleteStmt = mysqli_stmt_init($conn);
            if (mysqli_stmt_prepare($deleteStmt, $deleteSql)) {
                mysqli_stmt_bind_param($deleteStmt, "ssss", $fromGuid, $toGuid, $toGuid, $fromGuid);
                mysqli_stmt_execute($deleteStmt);
                mysqli_stmt_close($deleteStmt);
            }
            //Continue to create new request below
        } else {
            //Request already exists with Pending or Confirm status
            return false;
        }
    } else {
        mysqli_stmt_close($checkStmt);
    }
    
    //Insert new friend request with auto-generated request_guid
    $sql = "INSERT INTO addrequest (request_guid, request_from_guid, request_to_guid, request_status, request_notification_status, request_update_time, request_datetime)
            VALUES (uuid_to_bin(uuid(), true), uuid_to_bin(?, true), uuid_to_bin(?, true), ?, ?, STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'))";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ssssss", $fromGuid, $toGuid, $status, $notificationStatus, $currentDate, $currentDate);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    return true;
}

//Create a friend request record without checking for existing requests
function createFriendRequestRecord(string $fromGuid, string $toGuid, string $status, string $notificationStatus): bool
{
    $conn = getDbConnection();
    $currentDate = date("d/m/Y H:i:s");
    
    $sql = "INSERT INTO addrequest (request_guid, request_from_guid, request_to_guid, request_status, request_notification_status, request_update_time, request_datetime)
            VALUES (uuid_to_bin(uuid(), true), uuid_to_bin(?, true), uuid_to_bin(?, true), ?, ?, STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'), STR_TO_DATE(?, '%d/%m/%Y %H:%i:%s'))";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        app_log("ERROR: Failed to prepare createFriendRequestRecord: " . mysqli_error($conn));
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ssssss", $fromGuid, $toGuid, $status, $notificationStatus, $currentDate, $currentDate);
    if (!mysqli_stmt_execute($stmt)) {
        app_log("ERROR: Failed to execute createFriendRequestRecord: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);
    return true;
}

/* Get the conversation ID (request_guid) for two users.
Returns the smaller request_guid to ensure deterministic folder naming. */
function getConversationId(string $userGuid1, string $userGuid2): ?string
{
    $conn = getDbConnection();
    
    /* Query for the friendship request (either direction).
    ORDER BY request_guid ASC to always get the smaller one. */
    $sql = "SELECT bin_to_uuid(request_guid, true) as request_guid
            FROM addrequest
            WHERE ((request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true))
                OR (request_from_guid = uuid_to_bin(?, true) AND request_to_guid = uuid_to_bin(?, true)))
            AND request_status = 'Confirm'
            ORDER BY request_guid ASC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        app_log("ERROR: Failed to prepare conversation ID query: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("ssss", $userGuid1, $userGuid2, $userGuid2, $userGuid1);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && isset($row['request_guid'])) {
        return $row['request_guid'];
    }
    
    app_log("WARNING: No confirmed friendship found between $userGuid1 and $userGuid2");
    return null;
}