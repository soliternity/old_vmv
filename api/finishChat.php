<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
include '../dbconn.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

$app_user_id = $data['app_user_id'];

if (!isset($app_user_id)) {
    echo json_encode(['error' => 'User ID is required.']);
    exit();
}

// Update the conversation status to 'done'
$sql_update = "UPDATE conversation SET status = 'done' WHERE app_user_id = '$app_user_id' AND status = 'not_done'";
if ($conn->query($sql_update) === TRUE) {
    echo json_encode(['success' => true, 'message' => 'Chat finished.']);
} else {
    echo json_encode(['error' => 'Failed to finish chat.']);
}

$conn->close();
?>