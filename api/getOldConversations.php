<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include '../dbconn.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$app_user_id = $data['app_user_id'];

if (!isset($app_user_id)) {
    echo json_encode(['error' => 'User ID is required.']);
    exit();
}

// Fetch all 'done' conversations for the user
$sql_conversations = "SELECT 
                        c.conversation_id, 
                        CONCAT(s.fname, ' ', s.lname) AS staff_name, 
                        m.content AS last_message, 
                        m.sent_at AS time
                    FROM conversation c
                    JOIN staff s ON c.staff_id = s.id
                    LEFT JOIN message m ON c.conversation_id = m.conversation_id
                    WHERE c.app_user_id = '$app_user_id' AND c.status = 'done'
                    GROUP BY c.conversation_id
                    ORDER BY m.sent_at DESC";

$result = $conn->query($sql_conversations);

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}

echo json_encode(['conversations' => $conversations]);

$conn->close();
?>