<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // User is not logged in
    $is_logged_in = false;
    $user_data = null;
} else {
    // User is logged in
    $is_logged_in = true;
    $user_data = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'fname' => $_SESSION['fname'] ?? null,
        'lname' => $_SESSION['lname'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null
    ];
}

echo json_encode([
    'is_logged_in' => $is_logged_in,
    'user_data' => $user_data
]);
?>