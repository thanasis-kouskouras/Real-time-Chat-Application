<?php
include(dirname(__FILE__) . '/../dbh.inc.php');

function createAttachment($filename, $filetype): array
{
    $ext = getFileExtension($filename);
    $error = "Failed to create Attachment. Please try again.";
    $conn = $GLOBALS['_mc'];
    $sql = "INSERT INTO attachments (name, mimetype, extension) VALUES (?,?, ?)";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, "sss", $filename, $filetype, $ext);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        $insertId = $stmt->insert_id;
        mysqli_stmt_close($stmt);
    }
    return array($insertId, "");
}

function getAttachmentById($id): bool|array|string|null
{
    $field = 'guid = uuid_to_bin(?, true)';
    $error = "Failed to get Attachment. Please try again.";
    $type = "s";
    if (my_is_int($id)) {
        $field = 'Id = ?';
        $type = "i";
    }
    $conn = $GLOBALS['_mc'];
    $sql = "SELECT name, bin_to_uuid(guid, true) as guid, Id, mimetype, extension 
            FROM attachments WHERE " . $field;
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    } else {
        mysqli_stmt_bind_param($stmt, $type, $id);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return array(false, $error);
        }
        $resultData = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($resultData);
        mysqli_stmt_close($stmt);
    }
    return [$row, ""];
}
