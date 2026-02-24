<?php

// Set the content type to JSON
header('Content-Type: application/json');

require_once '../dbconn.php';
require_once '../adtLogs.php'; // Include the audit log function

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

// Decode the JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Check for required data
if (empty($data['job_id'])) {
    echo json_encode(['success' => false, 'message' => 'Job ID is required.']);
    exit();
}

$job_id = $data['job_id'];

// SQL query to update the status to 'Void' for soft deletion
$sql = "UPDATE jobs SET status = 'Void' WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // --- AUDIT LOGGING ---
    // Note: Replace 1 with the actual staff_id from your session or context
    $staff_id = $_SESSION['staff_id'] ?? 1;
    $title = "Marked job order #{$job_id} as Void";
    insert_audit_log($staff_id, 'archive', $title, 'jobs', $job_id, null, null);
    // --- END AUDIT LOGGING ---
    
    echo json_encode(['success' => true, 'message' => 'Job order status updated to Void.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update job order status. Job ID not found or already void.']);
}

$stmt->close();
$conn->close();
?>