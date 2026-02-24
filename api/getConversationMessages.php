<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
include '../dbconn.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$conversation_id = $data['conversation_id'];

if (!isset($conversation_id)) {
    echo json_encode(['error' => 'Conversation ID is required.']);
    exit();
}

// Fetch all messages for the specified conversation
$sql_messages = "SELECT sender_type, content, sent_at FROM message WHERE conversation_id = '$conversation_id' ORDER BY sent_at ASC";
$result = $conn->query($sql_messages);

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(['messages' => $messages]);

$conn->close();
?>