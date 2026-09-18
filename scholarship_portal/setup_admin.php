<?php
require_once 'includes/db_connect.php';

// Change these values to whatever you want your admin login to be
$admin_email = "admin@gmail.com";
$admin_password = "admin@123";

// Securely hash the password just like the registration page does
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, 'admin')");
    $stmt->execute([':email' => $admin_email, ':hash' => $hashed_password]);
    echo "<h1>Admin account created successfully!</h1>";
    echo "<p>Email: $admin_email</p>";
    echo "<p>Password: $admin_password</p>";
    echo "<p style='color:red;'>SECURITY WARNING: Please delete this setup_admin.php file immediately.</p>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . " (The account may already exist).";
}
?>