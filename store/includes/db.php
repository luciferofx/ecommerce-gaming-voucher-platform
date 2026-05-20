<?php
// includes/db.php
$host = 'localhost';
$dbname = 'dark_gaming';
$username = 'root';
$password = ''; // Default XAMPP password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "<br>Please run setup_db.php first.");
}

session_start(); // Start session for user state
?>