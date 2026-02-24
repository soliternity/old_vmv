<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: origin, content-type, accept, x-requested-with');

require_once '../dbconn.php';

// Check if all parameters are provided
if (isset($_GET['staff_id']) && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $staffId = $_GET['staff_id'];
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];

    $sql = "
        SELECT
            js.job_id,
            j.display_id,
            js.cost,
            js.hours_taken,
            js.completed_at,
            s.fname AS completed_by_fname,
            s.lname AS completed_by_lname,
            sv.name AS service_name
        FROM job_service js
        JOIN jobs j ON js.job_id = j.id
        JOIN staff s ON js.completed_by = s.id
        JOIN services sv ON js.service_id = sv.id
        WHERE js.completed_by = ?
        AND DATE(js.completed_at) BETWEEN ? AND ?
        ORDER BY js.completed_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $staffId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobEntries = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $jobEntries[] = [
                'jobId' => $row['display_id'],
                'serviceName' => $row['service_name'],
                'cost' => $row['cost'],
                'hours' => $row['hours_taken'],
                'completedAt' => $row['completed_at'],
                'completedBy' => $row['completed_by_fname'] . ' ' . $row['completed_by_lname']
            ];
        }
    }

    echo json_encode(["status" => "success", "data" => $jobEntries]);

    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
}

$conn->close();
?>