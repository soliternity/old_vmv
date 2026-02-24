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

// Check for existing unfinished conversation
$sql_check = "SELECT conversation_id, staff_id FROM conversation WHERE app_user_id = '$app_user_id' AND status = 'not_done'";
$result_check = $conn->query($sql_check);

if ($result_check->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Unfinished conversation exists.']);
    exit();
}

// Find an online mechanic (last_active within 5 minutes)
$five_minutes_ago = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$sql_online_mechanic = "SELECT id FROM staff WHERE role = 'mechanic' AND last_active >= '$five_minutes_ago' ORDER BY RAND() LIMIT 1";
$result_online_mechanic = $conn->query($sql_online_mechanic);

$staff_id = null;
if ($result_online_mechanic->num_rows > 0) {
    $mechanic = $result_online_mechanic->fetch_assoc();
    $staff_id = (int)$mechanic['id'];
} else {
    // If no online mechanics are found, default to a random mechanic
    $sql_any_mechanic = "SELECT id FROM staff WHERE is_archived = 0 AND role = 'mechanic' ORDER BY RAND() LIMIT 1";
    $result_any_mechanic = $conn->query($sql_any_mechanic);
    if ($result_any_mechanic->num_rows > 0) {
        $mechanic = $result_any_mechanic->fetch_assoc();
        $staff_id = (int)$mechanic['id'];
    }
}

if ($staff_id === null) {
    echo json_encode(['error' => 'No mechanics available. Please try again later.']);
    exit();
}

// Create a new conversation
$sql_insert = "INSERT INTO conversation (app_user_id, staff_id) VALUES (?, ?)";
$stmt = $conn->prepare($sql_insert);
$stmt->bind_param("ii", $app_user_id, $staff_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'New chat created successfully.']);
} else {
    echo json_encode(['error' => 'Failed to create new chat.']);
}

$stmt->close();
$conn->close();
?>