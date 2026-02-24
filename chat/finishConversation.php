<?php
session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../dbconn.php';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$conversation_id = isset($data['conversation_id']) ? intval($data['conversation_id']) : 0;

if ($conversation_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit();
}

// Check if the conversation belongs to the logged-in staff for security
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$staff_id = $_SESSION['user_id'];
$check_sql = "SELECT 1 FROM conversation WHERE conversation_id = ? AND staff_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $conversation_id, $staff_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this conversation']);
    exit();
}

// Update conversation status
$new_status = 'done';
$sql = "UPDATE conversation SET status = ? WHERE conversation_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $new_status, $conversation_id);
$result = mysqli_stmt_execute($stmt);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update conversation status']);
}

mysqli_close($conn);
?>