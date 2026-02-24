<?php
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
session_start();

require_once '../dbconn.php';

// Assume staff_id is stored in the session after login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$staff_id = $_SESSION['user_id'];

// Get conversations for the logged-in staff
$sql = "SELECT c.conversation_id, u.fname, u.lname FROM conversation c JOIN appusers u ON c.app_user_id = u.id WHERE c.staff_id = ? AND c.status = 'not_done'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $staff_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$conversations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $conversations[] = [
        'conversation_id' => $row['conversation_id'],
        'app_user_name' => htmlspecialchars($row['fname'] . ' ' . $row['lname']),
    ];
}

echo json_encode(['conversations' => $conversations]);

mysqli_close($conn);
?>