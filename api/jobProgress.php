<?php
// Allow cross-origin requests for development
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../dbconn.php';

// Get the posted JSON data from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Check if 'app_user_id' is provided in the request
if (isset($data['app_user_id'])) {
    $userId = $data['app_user_id'];
    $response = ['success' => true, 'jobs' => []];

    // SQL query to fetch job and service details for a given user
    $sql = "
        SELECT 
            j.display_id,
            j.customer_name,
            j.vehicle_brand,
            j.vehicle_color,
            j.vehicle_plate,
            j.created_at,
            j.status AS job_status,
            s.name AS service_name,
            js.notes,
            js.hours_taken,
            js.started_at,
            js.completed_at
        FROM 
            jobs j
        JOIN 
            job_service js ON j.id = js.job_id
        JOIN
            services s ON js.service_id = s.id
        WHERE 
            j.au_id = ?
        ORDER BY 
            j.created_at DESC, s.name ASC
    ";
    
    // Prepare the statement
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => "Failed to prepare statement: " . $conn->error]);
        exit();
    }
    
    // Bind parameters and execute
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    // Check for execution errors
    $result = $stmt->get_result();
    if ($result === false) {
        echo json_encode(['success' => false, 'error' => "Failed to execute query: " . $stmt->error]);
        exit();
    }

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $displayId = $row['display_id'];

        if (!isset($jobs[$displayId])) {
            $jobs[$displayId] = [
                'job_id' => $displayId,
                'job_status' => $row['job_status'],
                'customer_name' => $row['customer_name'],
                'vehicle_brand' => $row['vehicle_brand'],
                'vehicle_color' => $row['vehicle_color'],
                'vehicle_plate' => $row['vehicle_plate'],
                'created_at' => $row['created_at'],
                'services' => []
            ];
        }

        $jobs[$displayId]['services'][] = [
            'service_name' => $row['service_name'],
            'notes' => $row['notes'],
            'hours_taken' => $row['hours_taken'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
        ];
    }

    $response['jobs'] = array_values($jobs);
    echo json_encode($response);
    
    $stmt->close();

} else {
    // If 'app_user_id' is missing from the request body
    echo json_encode(['success' => false, 'error' => 'No user ID provided in the request.']);
}

$conn->close();
?>