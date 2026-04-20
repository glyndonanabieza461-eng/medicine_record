<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "medicine";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset (important for security & proper text handling)
$conn->set_charset("utf8mb4");
?>