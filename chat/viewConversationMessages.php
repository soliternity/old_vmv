<?php
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
session_start();

require_once '../dbconn.php';

// Get conversation_id from GET request
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;

if ($conversation_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation ID']);
    exit();
}

// Check if the conversation belongs to the logged-in staff
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

// Get all messages for the conversation, including the timestamp
$sql = "SELECT sender_type, content, sent_at FROM message WHERE conversation_id = ? ORDER BY sent_at ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $conversation_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'sender_type' => $row['sender_type'],
        'content' => htmlspecialchars($row['content']),
        'sent_at' => $row['sent_at'] // Add the timestamp to the response
    ];
}

echo json_encode(['messages' => $messages]);

mysqli_close($conn);
?>