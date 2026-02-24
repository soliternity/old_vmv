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

if (!isset($data['app_user_id'])) {
    echo json_encode(['error' => 'User ID is required.']);
    exit();
}

$app_user_id = $data['app_user_id'];

// Find the active conversation for the user
$sql_conversation = "SELECT 
                        c.conversation_id, 
                        c.staff_id, 
                        CONCAT(s.fname, ' ', s.lname) AS staff_name, 
                        s.last_active 
                    FROM conversation c
                    JOIN staff s ON c.staff_id = s.id
                    WHERE c.app_user_id = ? AND c.status = 'not_done'";

$stmt = $conn->prepare($sql_conversation);
$stmt->bind_param("i", $app_user_id);
$stmt->execute();
$result_conversation = $stmt->get_result();

if ($result_conversation->num_rows == 0) {
    echo json_encode(['error' => 'No active conversation found.']);
    exit();
}

$conversation_data = $result_conversation->fetch_assoc();
$conversation_id = (int)$conversation_data['conversation_id'];
$staff_id = (int)$conversation_data['staff_id'];
$staff_name = $conversation_data['staff_name'];
$mechanic_last_active = $conversation_data['last_active'];

// Fetch all messages for the conversation
$sql_messages = "SELECT sender_type, content, sent_at FROM message WHERE conversation_id = ? ORDER BY sent_at ASC";
$stmt = $conn->prepare($sql_messages);
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result_messages = $stmt->get_result();

$messages = [];
while ($row = $result_messages->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode([
    'success' => true,
    'conversation_id' => $conversation_id,
    'staff_id' => $staff_id,
    'staff_name' => $staff_name,
    'mechanic_last_active' => $mechanic_last_active,
    'messages' => $messages
]);

$stmt->close();
$conn->close();
?>