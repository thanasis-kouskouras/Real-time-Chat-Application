<?php

include(dirname(__FILE__) . '/../dbh.inc.php');
function getRelativeDownloadPath(){
    $conn = $GLOBALS['_mc'];
    $sql = "SELECT value as baseFileUrl FROM config  WHERE name = ?";
    $stmt = mysqli_stmt_init($conn);
    $name = "baseFileUrl";
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("location: ../signup.php?error=stmtfailed");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);

    $resultData = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultData);
    mysqli_stmt_close($stmt);
    return $row['baseFileUrl'];
}
