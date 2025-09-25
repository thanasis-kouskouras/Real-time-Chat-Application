<?php

include(dirname(__FILE__) . '/../dbh.inc.php');
require_once(dirname(__FILE__) . '/../functions.inc.php');
function getProfileImageByGuid($id): array
{
    list($user, $error) = getUserByGuid($id);
    if ($error!= "")
        return getProfileImage($user['UsersID']);
    else
        return array(false, $error);
}

function createDefaultProfileImage($id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed creating new profile image of user. Please try again.";
    $number = 1;
    $imageType = 'jpg';
    $sql = "INSERT INTO profileImage (UsersID, imgStatus, imgType) VALUES (?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        mysqli_stmt_close($stmt);
        return array(false, $error);
    }
    mysqli_stmt_bind_param($stmt, "sis", $id, $number, $imageType);
    if (!mysqli_stmt_execute($stmt))
    mysqli_stmt_close($stmt);
    return array(true, "");
}

function getProfileImage($id): array
{
    $conn = $GLOBALS['_mc'];
    $error = "Failed getting profile image of user. Please try again.";
    $sql = "SELECT * FROM profileImage WHERE UsersID = ?;";
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
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return array($row, "");
}

function getProfileImagePath($id): array
{
    $path = "img/profiledefault.jpg";
    if (my_is_int($id) === false) {
        list($img, $error) = getProfileImageByGuid($id);
    } else {
        list($img, $error) = getProfileImage($id);
    }
    if ($error == "" && $img['imgStatus'] == 0) {
        $path = "uploads/profile" . $img['UsersID'] . "." . $img['imgType'];
    }
    return array($path, $error);

}