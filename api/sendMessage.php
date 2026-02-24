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
$conversation_id = $data['conversation_id'];
$content = $conn->real_escape_string($data['content']);

if (!isset($app_user_id) || !isset($conversation_id) || !isset($content)) {
    echo json_encode(['error' => 'Missing required fields.']);
    exit();
}

// Insert the new message
$sql_insert = "INSERT INTO message (conversation_id, sender_type, content) VALUES ('$conversation_id', 'user', '$content')";
if ($conn->query($sql_insert) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
} else {
    echo json_encode(['error' => 'Failed to send message.']);
}

$conn->close();
?>