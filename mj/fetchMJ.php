<?php
session_start();
header('Content-Type: application/json');

// Include the database connection file
require_once '../dbconn.php';

// Get search term from GET request
$searchTerm = $_GET['search'] ?? '';
// **FIX:** Get the dedicated job ID parameter
$jobIdParam = $_GET['job_id'] ?? '';

// Get the user ID from the session
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    die(json_encode(['status' => 'error', 'message' => 'User not authenticated.']));
}

try {
    // Base WHERE clause
    $whereClauses = ["jst.staff_id = ? AND j.status IN ('Pending', 'Ongoing', 'Completed')"];
    $paramTypes = "i";
    $bindParams = [&$userId];

    // **FIX:** Prioritize fetching a single job by ID
    if (!empty($jobIdParam)) {
        // If job_id is provided, filter specifically by display_id
        $whereClauses[] = "j.display_id = ?";
        $paramTypes .= "s";
        $bindParams[] = &$jobIdParam;
    } elseif (!empty($searchTerm)) {
        // Otherwise, use the general search logic for the list view
        $whereClauses[] = "(
            j.display_id LIKE ? OR 
            j.customer_name LIKE ? OR 
            j.vehicle_plate LIKE ?
        )";
        $searchParam = "%" . $searchTerm . "%";
        $paramTypes .= "sss";
        $bindParams[] = &$searchParam;
        $bindParams[] = &$searchParam;
        $bindParams[] = &$searchParam;
    }
    
    // Construct the full SQL query
    $sql = "SELECT 
                j.display_id, 
                j.customer_name, 
                j.vehicle_brand, 
                j.vehicle_color, 
                j.vehicle_plate, 
                j.status,
                j.created_at,
                s.name AS service_name,
                js.notes,
                js.hours_taken,
                js.started_at,
                js.completed_at,
                started_by_staff.fname AS started_by_fname,
                started_by_staff.lname AS started_by_lname,
                completed_by_staff.fname AS completed_by_fname,
                completed_by_staff.lname AS completed_by_lname,
                js.started_by,
                js.completed_by
            FROM 
                jobs j
            JOIN
                job_staff jst ON j.id = jst.job_id
            LEFT JOIN 
                job_service js ON j.id = js.job_id
            LEFT JOIN
                services s ON js.service_id = s.id
            LEFT JOIN
                staff started_by_staff ON js.started_by = started_by_staff.id
            LEFT JOIN
                staff completed_by_staff ON js.completed_by = completed_by_staff.id
            WHERE " . implode(" AND ", $whereClauses);

    $sql .= " ORDER BY j.created_at DESC";

    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $stmt->bind_param($paramTypes, ...$bindParams);
    
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobId = $row['display_id'];
        
        // If job doesn't exist in our array, create it
        if (!isset($jobs[$jobId])) {
            $jobs[$jobId] = [
                'id' => $jobId,
                'customer' => $row['customer_name'],
                'vehicle' => [
                    'brand' => $row['vehicle_brand'],
                    'model' => null, 
                    'color' => $row['vehicle_color'],
                    'plate' => $row['vehicle_plate'],
                ],
                'services' => [],
                'status' => strtolower($row['status']),
                'dateCreated' => date('Y-m-d', strtotime($row['created_at'])),
            ];
        }

        // Add services to the job
        if ($row['service_name']) {
            $serviceStatus = 'pending';
            $hours = null;
            $notes = null;
            $startedBy = null;
            $completedBy = null;
            $startedById = null;
            $completedById = null;

            if ($row['completed_at']) {
                $serviceStatus = 'completed';
                $hours = (float)$row['hours_taken'];
                $notes = $row['notes'];
                $completedBy = "{$row['completed_by_fname']} {$row['completed_by_lname']}";
                $completedById = $row['completed_by'];
            } else if ($row['started_at']) {
                $serviceStatus = 'ongoing';
                $startedBy = "{$row['started_by_fname']} {$row['started_by_lname']}";
                $startedById = $row['started_by'];
            }

            $jobs[$jobId]['services'][] = [
                'name' => $row['service_name'],
                'status' => $serviceStatus,
                'hours' => $hours,
                'notes' => $notes,
                'started_by' => $startedBy,
                'completed_by' => $completedBy,
                'started_by_id' => $startedById,
                'completed_by_id' => $completedById,
            ];
        }
    }

    // Convert associative array to a simple array for JSON
    $jobList = array_values($jobs);

    echo json_encode(['status' => 'success', 'data' => $jobList]);

    $stmt->close();
} catch (Exception $e) {
    // This is useful for debugging
    error_log("Database error in fetchMJ.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>