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

// Check for required data: ONLY job_id, services, and mechanics are required for an update
if (empty($data['job_id']) || !isset($data['services']) || !isset($data['mechanics'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data: Job ID, services, or mechanics.']);
    exit();
}

$conn->begin_transaction();

try {
    $job_id = $data['job_id'];

    // --- AUDIT LOGGING: Fetch old data before update ---
    $old_data_query = "SELECT * FROM jobs WHERE id = ?";
    $stmt_old = $conn->prepare($old_data_query);
    $stmt_old->bind_param("i", $job_id);
    $stmt_old->execute();
    $old_data_result = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    // --- MECHANIC JOB LIMIT CHECK (Max 3 active jobs, EXCLUDING the current job being edited) ---
    $max_jobs = 3;
    $busy_mechanics = [];
    
    // Query excludes the job being edited ($job_id)
    $sql_limit = "SELECT COUNT(t1.job_id) AS active_jobs 
                  FROM job_staff t1 
                  INNER JOIN jobs t2 ON t1.job_id = t2.id
                  WHERE t1.staff_id = ? 
                  AND t2.status IN ('Pending', 'Ongoing') 
                  AND t1.job_id != ?";
    
    $stmt_limit = $conn->prepare($sql_limit);
    
    foreach ($data['mechanics'] as $mechanic_id) {
        $stmt_limit->bind_param("ii", $mechanic_id, $job_id); // Bind mechanic_id and job_id
        $stmt_limit->execute();
        $result = $stmt_limit->get_result();
        $row = $result->fetch_assoc();
        $active_jobs = $row['active_jobs'];
        
        // If the number of *other* active jobs is 3, assigning this job will make it 4, so block it.
        if ($active_jobs >= $max_jobs) { 
            // Fetch the mechanic's name for a better error message
            $name_query = "SELECT fname, lname FROM staff WHERE id = ?";
            $stmt_name = $conn->prepare($name_query);
            $stmt_name->bind_param("i", $mechanic_id);
            $stmt_name->execute();
            $name_result = $stmt_name->get_result()->fetch_assoc();
            $stmt_name->close();
            
            $busy_mechanics[] = $name_result['fname'] . ' ' . $name_result['lname'];
        }
    }
    
    $stmt_limit->close();

    if (!empty($busy_mechanics)) {
        $conn->rollback(); 
        $mechanic_list = implode(', ', $busy_mechanics);
        // Restriction message wrapped in <p> tag
        echo json_encode(['success' => false, 'message' => "<p>The following mechanic(s) are already assigned to {$max_jobs} active jobs and cannot be assigned this job: {$mechanic_list}.</p>"]);
        exit();
    }
    // ----------------------------------------------------
    
    // 1. Delete existing services records for this job
    $sql_delete_services = "DELETE FROM job_service WHERE job_id = ?";
    $stmt_delete_services = $conn->prepare($sql_delete_services);
    $stmt_delete_services->bind_param("i", $job_id);
    $stmt_delete_services->execute();
    $stmt_delete_services->close();

    // 2. Delete existing staff records for this job
    $sql_delete_staff = "DELETE FROM job_staff WHERE job_id = ?";
    $stmt_delete_staff = $conn->prepare($sql_delete_staff);
    $stmt_delete_staff->bind_param("i", $job_id);
    $stmt_delete_staff->execute();
    $stmt_delete_staff->close();

    // 3. Re-insert new services
    $sql_insert_service = "INSERT INTO job_service (job_id, service_id) VALUES (?, ?)";
    $stmt_insert_service = $conn->prepare($sql_insert_service);

    foreach ($data['services'] as $service_id) {
        $stmt_insert_service->bind_param("ii", $job_id, $service_id);
        $stmt_insert_service->execute();
    }
    $stmt_insert_service->close();

    // 4. Re-insert new mechanics
    $sql_insert_staff = "INSERT INTO job_staff (job_id, staff_id) VALUES (?, ?)";
    $stmt_insert_staff = $conn->prepare($sql_insert_staff);

    foreach ($data['mechanics'] as $mechanic_id) {
        $stmt_insert_staff->bind_param("ii", $job_id, $mechanic_id);
        $stmt_insert_staff->execute();
    }
    $stmt_insert_staff->close();

    $conn->commit();
    
    // --- AUDIT LOGGING ---
    // Note: Replace 1 with the actual staff_id from your session or context
    $staff_id = $_SESSION['staff_id'] ?? 1;
    $title = "Updated job order #{$job_id} (Services/Mechanics only)";
    insert_audit_log($staff_id, 'update', $title, 'jobs', $job_id, json_encode($old_data_result), json_encode($data));
    
    echo json_encode(['success' => true, 'message' => 'Job order services and mechanics updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>