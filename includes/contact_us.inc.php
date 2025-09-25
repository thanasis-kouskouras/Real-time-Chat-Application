<?php
require '../protect.php';
require_once 'functions.inc.php';

$userid = $user["UsersID"];
$userUsername = $user["usersUsername"];
$userEmail = $user["usersEmail"];

if(isset($_POST["contact_us-submit"])){

    $nickname = filter_var($_POST["nickname"], FILTER_SANITIZE_STRING);
    $surname = filter_var($_POST["surname"], FILTER_SANITIZE_STRING);
    $subject = filter_var($_POST["subject"], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST["message"], FILTER_SANITIZE_STRING);


    $to = "your-email@gmail.com";  
    $headers = "From: ".$userEmail." <".$userEmail.">\r\n";
    $headers .= "Reply-to: ".$userEmail."\r\n";
    
    $trimNick = trim($nickname);
    $trimSur = trim($surname);
    $trimMsg = trim($message);
    $trimSbj = trim($subject);


    if(empty($nickname) !== false && empty($surname) !== false || $trimNick == '' &&  $trimSur == ''){
        if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
            $body = "You have received an e-mail from ".$userEmail.".\nUser's Username:".$userUsername.".\nUser's ID:".$userid.".\n\nMessage: \n".$message;

            mail($to, $subject, $body, $headers);
            header("Location: ../contact_us.php?error=mailSend");
            exit();
        }else if(empty($subject) !== false && empty($message) !== false || $trimSbj == '' &&  $trimMsg == ''){
            header("Location: ../contact_us.php?error=emptySubjectMessage");
            exit();
        }
        else if(empty($subject) !== false || $trimSbj == ''){
            header("Location: ../contact_us.php?error=emptySubject&message=$message");
            exit();
        }else if(empty($message) !== false || $trimMsg == ''){
        header("Location: ../contact_us.php?error=emptyMessage&subject=$subject");
        exit();
        }
	}
	else if(empty($nickname) !== false || $trimNick == ''){
        if(invalidSurname($surname) !== false){
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                header("Location: ../contact_us.php?error=invalidSurname&subject=$subject&message=$message");
                exit();
            }else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=invalidSurname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
                header("Location: ../contact_us.php?error=invalidSurname&subject=$subject");
                exit();
            }
        }else{
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                $body = "You have received an e-mail from ".$userEmail." named ".$surname.".\nUser's Username:".$userUsername.".\nUser's ID:".$userid.".\n\nMessage: \n".$message;
                
                mail($to, $subject, $body, $headers);
                header("Location: ../contact_us.php?error=mailSend");
                exit();
            }else if(empty($subject) !== false && empty($message) !== false || $trimSbj == '' &&  $trimMsg == ''){
                header("Location: ../contact_us.php?error=emptySubjectMessage&surname=$surname");
                exit();
            }
            else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=emptySubject&surname=$surname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
            header("Location: ../contact_us.php?error=emptyMessage&surname=$surname&subject=$subject");
            exit();
            }
        }		
	}
    else if(empty($surname) !== false || $trimSur == ''){
        if(invalidNickname($nickname) !== false){
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                header("Location: ../contact_us.php?error=invalidNickname&subject=$subject&message=$message");
                exit();
            }else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=invalidNickname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
                header("Location: ../contact_us.php?error=invalidNickname&subject=$subject");
                exit();
            }
        }else{
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                $body = "You have received an e-mail from ".$userEmail." named ".$nickname.".\nUser's Username:".$userUsername.".\nUser's ID:".$userid.".\n\nMessage: \n".$message;

                mail($to, $subject, $body, $headers);
                header("Location: ../contact_us.php?error=mailSend");
                exit();
            }else if(empty($subject) !== false && empty($message) !== false || $trimSbj == '' &&  $trimMsg == ''){
                header("Location: ../contact_us.php?error=emptySubjectMessage&nickname=$nickname");
                exit();
            }
            else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=emptySubject&nickname=$nickname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
            header("Location: ../contact_us.php?error=emptyMessage&nickname=$nickname&subject=$subject");
            exit();
            }
        }		
	}
    else if(!empty($nickname) !== false && !empty($surname) !== false &&  $trimNick !== '' &&  $trimSur !== ''){
        if(!invalidNickname($nickname) !== false && !invalidSurname($surname) !== false){
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){		
                $body = "You have received an e-mail from ".$userEmail." named ".$nickname." ".$surname.".\nUser's Username:".$userUsername.".\nUser's ID:".$userid.".\n\nMessage: \n".$message;

                mail($to, $subject, $body, $headers);
                header("Location: ../contact_us.php?error=mailSend");
                exit();
            }else if(empty($subject) !== false && empty($message) !== false || $trimSbj == '' &&  $trimMsg == ''){
                header("Location: ../contact_us.php?error=emptySubjectMessage&nickname=$nickname&surname=$surname");
                exit();
            }
            else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=emptySubject&nickname=$nickname&surname=$surname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
            header("Location: ../contact_us.php?error=emptyMessage&nickname=$nickname&surname=$surname&subject=$subject");
            exit();
            }
        }else if (invalidNickname($nickname) !== false && invalidSurname($surname) !== false){
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                header("Location: ../contact_us.php?error=invalidNickSurName&subject=$subject&message=$message");
                exit();
            }else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=invalidNickSurName&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
                header("Location: ../contact_us.php?error=invalidNickSurName&subject=$subject");
                exit();
            }
        }else if (invalidNickname($nickname) !== false){
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                header("Location: ../contact_us.php?error=invalidNickname&surname=$surname&subject=$subject&message=$message");
                exit();
            }else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=invalidNickname&surname=$surname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
                header("Location: ../contact_us.php?error=invalidNickname&surname=$surname&subject=$subject");
                exit();
            }
        }else if (invalidSurname($surname) !== false){
            if(!empty($subject) !== false && !empty($message) !== false &&  $trimSbj !== '' &&  $trimMsg !== ''){
                header("Location: ../contact_us.php?error=invalidSurname&nickname=$nickname&subject=$subject&message=$message");
                exit();
            }else if(empty($subject) !== false || $trimSbj == ''){
                header("Location: ../contact_us.php?error=invalidSurname&nickname=$nickname&message=$message");
                exit();
            }else if(empty($message) !== false || $trimMsg == ''){
                header("Location: ../contact_us.php?error=invalidSurname&nickname=$nickname&subject=$subject");
                exit();
            }
        }
	}
}
else{
    header("Location: ../contact_us.php");
    exit();
}