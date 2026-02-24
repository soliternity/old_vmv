<?php
session_start();
// --- MODIFICATION HERE ---
date_default_timezone_set('Asia/Manila'); // Changed from 'UTC' to 'Asia/Manila' (UTC+8)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../dbconn.php';
// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$conversation_id = isset($data['conversation_id']) ? intval($data['conversation_id']) : 0;
$content = isset($data['content']) ? $data['content'] : '';

if ($conversation_id <= 0 || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit();
}

// Check if the conversation belongs to the logged-in staff for security
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$staff_id = $_SESSION['user_id'];
$check_sql = "SELECT 1 FROM conversation WHERE conversation_id = ? AND staff_id = ? AND status = 'not_done'";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $conversation_id, $staff_id);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied or conversation already done']);
    exit();
}

// Insert message into the database
$sender_type = 'staff';
$sql = "INSERT INTO message (conversation_id, sender_type, content) VALUES (?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iss", $conversation_id, $sender_type, $content);
$result = mysqli_stmt_execute($stmt);

if ($result) {
    echo json_encode(['success' => true, 'content' => htmlspecialchars($content)]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}

mysqli_close($conn);
?>