<?php
$host = 'localhost';
$username = 'u157619782_d';
$password = '@VMVJeffix123';
$database = 'u157619782_d';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- ADDED: Set the database session timezone to UTC+8 ---
// The connection object ($conn) is used to execute the query.
$conn->query("SET time_zone = '+8:00'");

// Set charset to utf8
$conn->set_charset("utf8");
?>