<?php
// ============================================================
//  Train Food Ordering System — Database Connection
//  File: config/db.php
//  Include this file at the top of every PHP page that
//  needs to talk to the database.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // change to your MySQL username
define('DB_PASS', '');            // change to your MySQL password
define('DB_NAME', 'train_food_db');
define('DB_CHARSET', 'utf8mb4');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Set character set
$conn->set_charset(DB_CHARSET);

// Check connection
if ($conn->connect_error) {
    // In production: log error, show friendly message
    // In development: show full error
    die("Database connection failed: " . $conn->connect_error);
}
?>
