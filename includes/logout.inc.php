<?php

require '../protect.php';
require_once "functions.inc.php";

$userid = $user["UsersID"];
require 'dbh.inc.php';

if (logout($userid))
    header("location: ../login.php");
else{
    setSessionError("An error has occurred. Please try again.");
    header("location: ../index.php");
}

exit();