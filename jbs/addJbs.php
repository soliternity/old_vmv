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

// Get the au_id, default to null if not provided
$au_id = $data['au_id'] ?? null;

// Check for required data
if (empty($data['customer_name']) || empty($data['vehicle_brand']) || empty($data['vehicle_color']) || empty($data['vehicle_plate']) || !isset($data['services']) || !isset($data['mechanics'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit();
}

$conn->begin_transaction();

try {
    // --- Duplicate Job Check: Prevent creation if an active job (Pending or Ongoing) exists for this plate. ---
    $plate = $data['vehicle_plate'];
    $sql_check = "SELECT id FROM jobs WHERE vehicle_plate = ? AND status IN ('Pending', 'Ongoing')";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $plate);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        $conn->rollback(); 
        echo json_encode(['success' => false, 'message' => 'An active job order already exists for vehicle plate: ' . $plate]);
        exit();
    }
    $stmt_check->close();
    // --------------------------------

    // --- MECHANIC JOB LIMIT CHECK (Max 3 active jobs) ---
    $max_jobs = 3;
    $busy_mechanics = [];
    
    // Query to count active jobs for a mechanic
    $sql_limit = "SELECT COUNT(t1.job_id) AS active_jobs 
                  FROM job_staff t1 
                  INNER JOIN jobs t2 ON t1.job_id = t2.id
                  WHERE t1.staff_id = ? AND t2.status IN ('Pending', 'Ongoing')";
    
    $stmt_limit = $conn->prepare($sql_limit);
    
    foreach ($data['mechanics'] as $mechanic_id) {
        $stmt_limit->bind_param("i", $mechanic_id);
        $stmt_limit->execute();
        $result = $stmt_limit->get_result();
        $row = $result->fetch_assoc();
        $active_jobs = $row['active_jobs'];
        
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
        echo json_encode(['success' => false, 'message' => "<p>The following mechanic(s) are already assigned to {$max_jobs} active jobs: {$mechanic_list}. Please choose another mechanic or wait until a job is completed.</p>"]);
        exit();
    }
    // ----------------------------------------------------


    // 1. Insert into the jobs table
    $sql_jobs = "INSERT INTO jobs (display_id, au_id, customer_name, vehicle_brand, vehicle_color, vehicle_plate, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_jobs = $conn->prepare($sql_jobs);

    // Generate a unique display ID
    $display_id = 'J' . date('Ymd') . rand(1000, 9999);
    $status = 'Pending';
    
    // Corrected bind_param: 's' for display_id, 'i' for au_id, 's' for the rest
    $stmt_jobs->bind_param("sisssss", $display_id, $au_id, $data['customer_name'], $data['vehicle_brand'], $data['vehicle_color'], $data['vehicle_plate'], $status);
    $stmt_jobs->execute();

    $job_id = $conn->insert_id;
    $stmt_jobs->close();

    // 2. Insert into job_service table
    $sql_service = "INSERT INTO job_service (job_id, service_id) VALUES (?, ?)";
    $stmt_service = $conn->prepare($sql_service);

    foreach ($data['services'] as $service_id) {
        $stmt_service->bind_param("ii", $job_id, $service_id);
        $stmt_service->execute();
    }
    $stmt_service->close();

    // 3. Insert into job_staff table
    $sql_staff = "INSERT INTO job_staff (job_id, staff_id) VALUES (?, ?)";
    $stmt_staff = $conn->prepare($sql_staff);

    foreach ($data['mechanics'] as $mechanic_id) {
        $stmt_staff->bind_param("ii", $job_id, $mechanic_id);
        $stmt_staff->execute();
    }
    $stmt_staff->close();

    $conn->commit();

    // --- AUDIT LOGGING ---
    // Note: Replace 1 with the actual staff_id from your session or context
    $staff_id = $_SESSION['staff_id'] ?? 1;
    $title = "Created new job order #{$display_id}";
    insert_audit_log($staff_id, 'create', $title, 'jobs', $job_id, null, json_encode($data));

    echo json_encode(['success' => true, 'message' => 'Job order created successfully!', 'job_id' => $job_id, 'display_id' => $display_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Failed to create job order: ' . $e->getMessage()]);
}

$conn->close();
?>