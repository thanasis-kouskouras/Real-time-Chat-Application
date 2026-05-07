<?php

require_once(dirname(__FILE__) . '/../dbh.inc.php');


//Update attachment with chat context information
function updateAttachmentChatContext($attachmentId, $chatType, $user_guid1 = null, $user_guid2 = null, $groupGuid = null): array
{
    $error = "Failed to update attachment context. Please try again.";
    $conn = getDbConnection();

    //Convert GUIDs to binary format for storage
    $user_guid1Binary = null;
    $user_guid2Binary = null;
    $groupGuidBinary = null;
    
    if ($user_guid1 !== null) {
        try {
            $user_guid1Binary = guidToBytes($user_guid1);
        } catch (Exception $e) {
            return array(false, "Invalid user GUID 1: " . $e->getMessage());
        }
    }
    
    if ($user_guid2 !== null) {
        try {
            $user_guid2Binary = guidToBytes($user_guid2);
        } catch (Exception $e) {
            return array(false, "Invalid user GUID 2: " . $e->getMessage());
        }
    }
    
    if ($groupGuid !== null) {
        try {
            $groupGuidBinary = guidToBytes($groupGuid);
        } catch (Exception $e) {
            return array(false, "Invalid group GUID: " . $e->getMessage());
        }
    }
    
    $sql = "UPDATE attachments SET chat_type = ?, user_guid_1 = ?, user_guid_2 = ?, group_guid = ? WHERE guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    
    mysqli_stmt_bind_param($stmt, "sssss", $chatType, $user_guid1Binary, $user_guid2Binary, $groupGuidBinary, $attachmentId);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error . " SQL Error: " . mysqli_error($conn));
    }
    
    mysqli_stmt_close($stmt);
    return array(true, "");
}

//Get attachment by GUID identifier
function getAttachmentByGuid(string $guid): array
{
    $error = "Failed to get Attachment. Please try again.";
    
    //Validate GUID format
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $guid)) {
        return [false, "Invalid GUID format"];
    }
    
    $conn = getDbConnection();
    
    $sql = "SELECT name, bin_to_uuid(guid, true) as guid, mime_type as mimetype, file_extension as extension,
                   chat_type, 
                   CASE WHEN user_guid_1 IS NOT NULL THEN bin_to_uuid(user_guid_1, true) ELSE NULL END as user_guid_1,
                   CASE WHEN user_guid_2 IS NOT NULL THEN bin_to_uuid(user_guid_2, true) ELSE NULL END as user_guid_2,
                   CASE WHEN group_guid IS NOT NULL THEN bin_to_uuid(group_guid, true) ELSE NULL END as group_guid,
                   file_path
            FROM attachments WHERE guid = uuid_to_bin(?, true)";
    $stmt = mysqli_stmt_init($conn);
    
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return [false, $error];
    }
    
    mysqli_stmt_bind_param($stmt, "s", $guid);
    
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return [false, $error];
    }
    
    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    
    if (!$row) {
        return [false, "Attachment not found"];
    }
    
    return [$row, ""];
}