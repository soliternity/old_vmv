<?php
header('Content-Type: application/json');

require_once '../dbconn.php';

$response = ['success' => false, 'message' => ''];

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Get and sanitize username
$username = $_POST['username'] ?? '';
if (empty($username)) {
    $response['message'] = 'Username is required.';
    echo json_encode($response);
    exit;
}

// Prepare statement to check username and role
$sql = "SELECT id FROM staff WHERE username = ? AND role = 'admin' AND is_archived = FALSE";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $response['message'] = 'Database query failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($staff_id);
    $stmt->fetch();
    $response['success'] = true;
    $response['message'] = 'Username and role confirmed.';
    $response['staff_id'] = $staff_id;
} else {
    $response['message'] = 'Invalid username or user is not an admin.';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>