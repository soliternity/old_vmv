<?php
// ALLOW-CONTROL-HEADERS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
// Start the session to access $_SESSION variables
session_start();

// Set the response header to JSON
header('Content-Type: application/json');

// Include your database connection file
require_once 'dbconn.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$userId = $_SESSION['user_id'];

// Use a prepared statement to update the last_active timestamp
// Added an 8-hour interval to the timestamp to match the local time
$sql = "UPDATE staff SET last_active = DATE_ADD(NOW(), INTERVAL 8 HOUR) WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Last active time updated.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update last active time: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>