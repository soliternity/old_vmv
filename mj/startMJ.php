<?php
session_start();
header('Content-Type: application/json');

// Include the database connection file
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$jobDisplayId = $data['job_id'] ?? null;
$serviceName = $data['service_name'] ?? null;

if (empty($jobDisplayId) || empty($serviceName)) {
    die(json_encode(['status' => 'error', 'message' => 'Missing job or service ID.']));
}

// Get the user ID from the session
$startedBy = $_SESSION['user_id'] ?? null;

if (!$startedBy) {
    die(json_encode(['status' => 'error', 'message' => 'User not authenticated.']));
}

try {
    $conn->begin_transaction();

    // 1. Get the service ID and job ID from their names
    $sqlIds = "SELECT s.id AS service_id, j.id AS job_id FROM services s
            JOIN job_service js ON s.id = js.service_id
            JOIN jobs j ON js.job_id = j.id
            WHERE j.display_id = ? AND s.name = ?";
    $stmtIds = $conn->prepare($sqlIds);
    $stmtIds->bind_param("ss", $jobDisplayId, $serviceName);
    $stmtIds->execute();
    $resultIds = $stmtIds->get_result();
    $rowIds = $resultIds->fetch_assoc();
    $serviceId = $rowIds['service_id'] ?? null;
    $jobId = $rowIds['job_id'] ?? null;
    $stmtIds->close();

    if (!$serviceId || !$jobId) {
        $conn->rollback();
        die(json_encode(['status' => 'error', 'message' => 'Job or service not found.']));
    }

    // 2. Update the specific job service to start
    $sqlJobService = "UPDATE job_service
                    SET started_at = NOW(), started_by = ?
                    WHERE job_id = ? AND service_id = ?";
    $stmtJobService = $conn->prepare($sqlJobService);
    $stmtJobService->bind_param("iii", $startedBy, $jobId, $serviceId);
    $stmtJobService->execute();
    $stmtJobService->close();

    // 3. Update the main job status to 'Ongoing' if it was 'Pending'
    $sqlJobStatus = "UPDATE jobs SET status = 'Ongoing' WHERE display_id = ? AND status = 'Pending'";
    $stmtJobStatus = $conn->prepare($sqlJobStatus);
    $stmtJobStatus->bind_param("s", $jobDisplayId);
    $stmtJobStatus->execute();
    $stmtJobStatus->close();

    // 4. Insert audit log entry for the service being started
    insert_audit_log(
        $startedBy, 
        'update', 
        'Service Started', 
        'job_service', 
        $jobId, 
        ['service_name' => $serviceName], 
        ['status' => 'Ongoing'],
        "Service '{$serviceName}' started for Job #{$jobDisplayId}"
    );

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Service started successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>