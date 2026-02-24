<?php
// Set content type to application/json
header('Content-Type: application/json');

// Start the session to access $_SESSION['user_id']
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Database Connection and Staff ID Retrieval
// Use the required connection file
@include '../../dbconn.php'; 

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

// Get the user ID from the session, defaulting to 0 if not set (for safety)
$staffId = $_SESSION['user_id'] ?? 0;

// Initialize the final data array
$dashboardData = [
    'mockVehicles' => [],
    'mockServiceInfo' => [],
    'mockPartsInfo' => [],
    'mockChats' => []
];

// --- 2. Fetch Services/Repairs (mockServiceInfo) ---
$sqlServices = "
    SELECT
        name,
        min_cost,
        max_cost,
        min_hours,
        max_hours,
        description
    FROM
        services
    ORDER BY
        name ASC;
";
$resultServices = $conn->query($sqlServices);

if ($resultServices) {
    while ($row = $resultServices->fetch_assoc()) {
        $costRange = '₱' . number_format($row['min_cost'], 2) . ' - ₱' . number_format($row['max_cost'], 2);
        $hourRange = number_format($row['min_hours'], 1) . ' - ' . number_format($row['max_hours'], 1) . ' hrs';
        
        $dashboardData['mockServiceInfo'][] = [
            'name' => $row['name'],
            'hourRange' => $hourRange,
            'costRange' => $costRange,
            'description' => $row['description']
        ];
    }
    $resultServices->free();
}

// --- 3. Fetch Parts (mockPartsInfo) ---
$sqlParts = "
    SELECT
        name,
        cost
    FROM
        parts
    ORDER BY
        name ASC;
";
$resultParts = $conn->query($sqlParts);

if ($resultParts) {
    while ($row = $resultParts->fetch_assoc()) {
        $dashboardData['mockPartsInfo'][] = [
            'name' => $row['name'],
            'cost' => '₱' . number_format($row['cost'], 2)
        ];
    }
    $resultParts->free();
}

// --- 4. Fetch Jobs/Vehicles (mockVehicles) ---
// Note: Removed the status filter to fetch ALL assigned services for accurate stats.
$sqlJobs = "
    SELECT
        j.id AS job_id,
        j.vehicle_brand,
        j.vehicle_plate,
        js.display_id AS service_id,
        js.notes AS description,
        js.completed_at,  /* Service-level completion status */
        j.status AS job_status, /* Overall job status for priority/queuing */
        CASE
            WHEN j.status = 'Ongoing' THEN 'high'
            ELSE 'medium'
        END AS priority,
        DATE_FORMAT(DATE_ADD(j.created_at, INTERVAL 4 HOUR), '%h:%i %p') AS due
    FROM
        jobs j
    JOIN
        job_service js ON j.id = js.job_id
    JOIN
        job_staff jst ON j.id = jst.job_id
    WHERE
        jst.staff_id = ? 
    ORDER BY
        j.status DESC, j.updated_at DESC;
";

$stmtJobs = $conn->prepare($sqlJobs);
$stmtJobs->bind_param("i", $staffId); 
$stmtJobs->execute();
$resultJobs = $stmtJobs->get_result();

$vehicles = [];
if ($resultJobs) {
    while ($row = $resultJobs->fetch_assoc()) {
        $plate = $row['vehicle_plate'];
        
        // Determine service status based on completed_at column
        $serviceStatus = 'Queued';
        if ($row['completed_at'] !== null) {
            $serviceStatus = 'Completed';
        } else if ($row['job_status'] === 'Ongoing') {
            // If service is not completed, but the overall job is Ongoing, the service is In Progress
            $serviceStatus = 'In Progress';
        }
        
        // Group services by vehicle plate
        if (!isset($vehicles[$plate])) {
            $vehicles[$plate] = [
                'carBrand' => $row['vehicle_brand'],
                'plate' => $plate,
                'services' => []
            ];
        }
        
        $vehicles[$plate]['services'][] = [
            'id' => $row['service_id'],
            'description' => $row['description'] ?? 'No notes provided',
            'due' => $row['due'],
            'priority' => $row['priority'], 
            'status' => $serviceStatus // Using service-specific status
        ];
    }
    $dashboardData['mockVehicles'] = array_values($vehicles);
    $resultJobs->free();
}
$stmtJobs->close();


// --- 5. Fetch Chats (mockChats) ---
$sqlChats = "
    SELECT
        c.conversation_id,
        a.fname,
        a.lname
    FROM
        conversation c
    JOIN
        appusers a ON c.app_user_id = a.id
    WHERE
        c.status = 'not_done' AND c.staff_id = ? 
    GROUP BY
        c.conversation_id, a.fname, a.lname, c.updated_at
    ORDER BY
        c.updated_at DESC;
";

$stmtChats = $conn->prepare($sqlChats);
$stmtChats->bind_param("i", $staffId);
$stmtChats->execute();
$resultChats = $stmtChats->get_result();

if ($resultChats) {
    while ($row = $resultChats->fetch_assoc()) {
        
        $customerName = trim($row['fname'] . ' ' . $row['lname']);
        
        $dashboardData['mockChats'][] = [
            'name' => $customerName ?? 'Customer ' . $row['conversation_id'],
        ];
    }
    $resultChats->free();
}
$stmtChats->close();

// --- 6. Output JSON and Close Connection ---
$conn->close();

echo json_encode($dashboardData, JSON_PRETTY_PRINT);

?>