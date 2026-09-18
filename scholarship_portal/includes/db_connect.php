<?php
// includes/db_connect.php

$host = "localhost";
$dbname = "scholarship_db";
$username = "root"; // Default XAMPP username
$password = "";     // Default XAMPP password is empty

try {
    // We use PDO for secure, modern database connections
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set PDO error mode to exception so we can see errors if they happen
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>