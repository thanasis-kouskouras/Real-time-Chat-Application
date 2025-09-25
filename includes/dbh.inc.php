<?php

$servername = "localhost";  
$username = "your_server_username"; // Your MySQL username
$password = "your_secure_password"; // Your MySQL password
$dbname = "your_database_name";     // Your database name

$conn = mysqli_connect($serverName, $dBUsername, $dBPassword, $dBName);
$GLOBALS['_mc'] = $conn;
$sql = "SET GLOBAL time_zone = 'Europe/Athens';";
mysqli_query($conn, $sql);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}