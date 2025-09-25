<?php
require '../protect.php';

if (!isset($_SESSION["UserID"])){
    header("location: ../login.php");
	exit();
}
require "dbh.inc.php";
$userid = $_SESSION["UserID"];



if(isset($_POST["profile-img-submit"])){
    $file = $_FILES["file"];

    $fileName = $file["name"];
    $fileTmpName = $file["tmp_name"];
    $fileSize = $file["size"];
    $fileError = $file["error"];

    $fileExt = explode('.', $fileName);
    $fileActualExt = strtolower(end($fileExt));
    
    $imageType = $fileActualExt;
    
    $allowed = array("jpg", "jpeg", "png");

    if (in_array($fileActualExt, $allowed)) {
        if($fileError === 0){
            if ($fileSize < 10000000) {
                $fileNameNew = "profile".$userid.".".$fileActualExt;

                $fileDestination = "../uploads/".$fileNameNew;
                move_uploaded_file($fileTmpName, $fileDestination);

                $sql = "UPDATE profileImage SET imgStatus = 0  WHERE UsersID = '$userid';";
                mysqli_query($conn, $sql);
                $sqlType = "UPDATE profileImage SET  imgType = '$imageType' WHERE UsersID = '$userid';";
                mysqli_query($conn, $sqlType); 

                header("Location: ../profile.php?error=none");
            }
            else{               
                header("Location: ../profile.php?error=bigFile");
            }
        }
        else{            
            header("Location: ../profile.php?error=uploadError");
        }
    }
    else{      
        header("Location: ../profile.php?error=wrongType");
    }
    exit();
}
else if(isset($_POST["delete-profile-img-submit"])){

    $sqlDeleted = "SELECT * FROM profileImage WHERE UsersID = '$userid';";
    $sqlDeletedResult = mysqli_query($conn, $sqlDeleted);
    $DeletedImg = mysqli_fetch_assoc($sqlDeletedResult);
    if($DeletedImg['imgStatus'] == 1 ){
        header("Location: ../profile.php?error=deletedImg");
        exit();
    }

    $fileName = "../uploads/profile".$userid."*";
    $fileInfo = glob($fileName);
    $fileExt = explode('.', $fileInfo[0]);
    $fileActualExt = end($fileExt);

    $file = "../uploads/profile".$userid.".".$fileActualExt;
    
    if(!unlink($file)){
        header("Location: ../profile.php?error=notdeleted");
    }
    
    $sql = "UPDATE profileImage SET imgStatus = 1 WHERE UsersID = '$userid';";
    mysqli_query($conn, $sql);
    $sqlType = "UPDATE profileImage SET  imgType = 'jpg' WHERE UsersID = '$userid';";
    mysqli_query($conn, $sqlType); 
    header("Location: ../profile.php?error=successdelete");       
}
else{
    header("Location: ../profile.php");
}