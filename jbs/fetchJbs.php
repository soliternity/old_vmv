<?php
session_start();

// Set the content type to JSON
header('Content-Type: application/json');



require_once '../dbconn.php';

// Prepare the main query to get all jobs with the specified statuses
$sql = "SELECT 
    j.id, 
    j.display_id,
    j.customer_name,
    j.vehicle_brand,
    j.vehicle_color,
    j.vehicle_plate,
    j.status,
    j.created_at
FROM 
    jobs AS j
WHERE 
    j.status IN ('Pending', 'Ongoing', 'Completed')
ORDER BY 
    j.created_at DESC";

$result = $conn->query($sql);

$job_orders = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Prepare a sub-query to fetch services for the current job, including the service ID
        $services_sql = "SELECT 
            s.id,
            s.name, 
            s.min_cost, 
            s.max_cost
        FROM 
            job_service AS js
        JOIN 
            services AS s ON js.service_id = s.id
        WHERE 
            js.job_id = ?";
        
        $stmt_services = $conn->prepare($services_sql);
        $stmt_services->bind_param("i", $row['id']);
        $stmt_services->execute();
        $services_result = $stmt_services->get_result();

        $services = [];
        while ($service_row = $services_result->fetch_assoc()) {
            $services[] = $service_row;
        }
        $stmt_services->close();

        // Prepare a sub-query to fetch staff for the current job, including the staff ID
        $staff_sql = "SELECT 
            s.id,
            CONCAT(s.fname, ' ', s.lname) AS staff_name
        FROM 
            job_staff AS js
        JOIN 
            staff AS s ON js.staff_id = s.id
        WHERE 
            js.job_id = ?";
        
        $stmt_staff = $conn->prepare($staff_sql);
        $stmt_staff->bind_param("i", $row['id']);
        $stmt_staff->execute();
        $staff_result = $stmt_staff->get_result();
        
        $staff_members = [];
        while ($staff_row = $staff_result->fetch_assoc()) {
            $staff_members[] = [
                'id' => $staff_row['id'],
                'name' => $staff_row['staff_name']
            ];
        }
        $stmt_staff->close();

        // Build the final job order object
        $job_orders[] = [
            'id' => $row['id'],
            'display_id' => $row['display_id'],
            'customer_name' => $row['customer_name'],
            'vehicle' => [
                'brand' => $row['vehicle_brand'],
                'color' => $row['vehicle_color'],
                'plate' => $row['vehicle_plate']
            ],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'services' => $services,
            'mechanics' => $staff_members
        ];
    }
}

// Close the main database connection
$conn->close();

// Output the JSON
echo json_encode($job_orders, JSON_PRETTY_PRINT);
?>