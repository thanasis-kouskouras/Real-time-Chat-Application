<?php

require_once(dirname(__DIR__) . '/config.php');

function localhost()
{
    //The function uses centralized configuration from config.php
    $conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
    
    if ($conn) {
        mysqli_set_charset($conn, DB_CHARSET);
    }
    
    return $conn;
}

function createDbConnection(): mysqli {
    $conn = localhost();
    return $conn;
}

function getDbConnection(): mysqli {
    static $conn = null;

    //First call, create connection
    if ($conn === null) {
        $conn = createDbConnection();
        return $conn;
    }

    //Later calls, check if it's still alive
    $alive = true;

    try {
        $alive = @$conn->ping(); //@ to avoid noisy warnings in logs if ping() return false
    } catch (\Throwable $e) {
        $alive = false;
    }

    if (!$alive) {
        //Old connection is dead, so close connection and recreate it
        try {
            $conn->close();
        } catch (\Throwable $ignore) {
            //Ignore close failures
        }
        $conn = createDbConnection();
    }

    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $conn;
}