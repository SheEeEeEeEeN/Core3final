<?php
// Determine if we are on localhost or a live server
$is_localhost = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['HTTP_HOST'] === 'localhost');

if ($is_localhost) {
    // LOCAL SETTINGS
    $db_host = "localhost";
    $db_user = "root";
    $db_pass = "";
    $db_name = "core_slate1";
} else {
    // LIVE SERVER SETTINGS
    $db_host = "localhost";
    $db_name = "core3_slate";
    $db_user = "core3_core3slateph";
    $db_pass = "corerakot3";
}

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // If local, show the error. If live, show a generic message.
    if ($is_localhost) {
        die("Connection failed: " . $conn->connect_error);
    } else {
        error_log("Database Connection Failed: " . $conn->connect_error);
        die("Sorry, we're having some technical difficulties. Please try again later.");
    }
}

// ✅ Set timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// No closing ?>