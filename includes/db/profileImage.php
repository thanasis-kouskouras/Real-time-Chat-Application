<?php

require_once(dirname(__FILE__) . '/../dbh.inc.php');
require_once(dirname(__FILE__) . '/../guid-utilities.php');

//Get profile image URL
function getProfileImageUrlByGuid(string $user_guid): string
{
    require_once __DIR__ . '/../upload/UploadController.php';

    try {
        $uploadController = new UploadController(getDbConnection());
        return $uploadController->getProfileImageUrl($user_guid);
    } catch (Exception $e) {
        app_log("Error getting profile image URL: " . $e->getMessage());
        return 'img/profiledefault.jpg';
    }
}

//Get profile image information
function getProfileImageInfoByGuid(string $user_guid): array|false
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("
        SELECT pi.image_guid, pi.image_type
        FROM profileImage pi
        INNER JOIN users u ON pi.user_guid = u.user_guid
        WHERE u.user_guid = uuid_to_bin(?, true)
        LIMIT 1
    ");
    $stmt->bind_param("s", $user_guid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row;
    }

    return false;
}

//Create default profile image entry for a new user
function createDefaultProfileImageByGuid($user_guid): array
{
    $conn = getDbConnection();
    $error = "Failed creating new profile image of user. Please try again.";
    $imageType = 'jpg';

    $imageGuid = generateGuid();

    $sql = "INSERT INTO profileImage (image_guid, user_guid, image_type) VALUES (uuid_to_bin(?, true), uuid_to_bin(?, true), ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "sss", $imageGuid, $user_guid, $imageType);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_close($stmt);
    return array(true, "");
}