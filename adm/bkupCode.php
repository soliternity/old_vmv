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

// Get and validate input
$staff_id = $_POST['staff_id'] ?? 0;
$backup_code = $_POST['backup_code'] ?? '';

if (empty($staff_id) || empty($backup_code)) {
    $response['message'] = 'Staff ID and backup code are required.';
    echo json_encode($response);
    exit;
}

// Sanitize inputs
$staff_id = (int)$staff_id;

// Prepare statement to check backup code
$sql = "SELECT code_hash, is_used FROM backup_codes WHERE staff_id = ? AND is_used = FALSE";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    $response['message'] = 'Database query failed: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param('i', $staff_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($code_hash, $is_used);
    $stmt->fetch();
    
    // Verify the backup code hash
    if (password_verify($backup_code, $code_hash)) {
        $response['success'] = true;
        $response['message'] = 'Backup code is valid.';
    } else {
        $response['message'] = 'Invalid backup code.';
    }
} else {
    $response['message'] = 'Invalid backup code or backup code already used.';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>