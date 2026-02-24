<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../dbconn.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['app_user_id']) || empty($data['app_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in to view your transaction history']);
    exit;
}

$app_user_id = $data['app_user_id'];

$sql = "SELECT 
    j.id AS job_id,
    j.customer_name,
    j.vehicle_brand,
    j.vehicle_color,
    j.vehicle_plate,
    j.status AS job_status,
    DATE_FORMAT(j.created_at, '%Y-%m-%d') AS job_date,
    j.updated_at,
    i.id AS invoice_id,
    i.total_amount AS total_cost,
    p.amount AS payment_amount,
    p.change_given AS payment_change
FROM jobs j
LEFT JOIN invoices i ON j.id = i.job_id
LEFT JOIN payments p ON i.id = p.invoice_id
WHERE j.au_id = ? AND j.status = 'Paid'
ORDER BY j.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $app_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $job_id = $row['job_id'];
        $invoice_id = $row['invoice_id'];

        // Fetch services for the current job
        $services_sql = "SELECT s.name AS service_name, js.cost AS service_cost
            FROM job_service js
            JOIN services s ON js.service_id = s.id
            WHERE js.job_id = ?";
        
        $services = [];
        if ($services_stmt = $conn->prepare($services_sql)) {
            $services_stmt->bind_param("i", $job_id);
            $services_stmt->execute();
            $services_result = $services_stmt->get_result();
            while ($service_row = $services_result->fetch_assoc()) {
                $services[] = $service_row;
            }
            $services_stmt->close();
        }

        // Fetch staff for the current job
        $staff_sql = "SELECT CONCAT(s.fname, ' ', s.lname) AS staff_name
            FROM job_staff jst
            JOIN staff s ON jst.staff_id = s.id
            WHERE jst.job_id = ?";
        
        $staff = [];
        if ($staff_stmt = $conn->prepare($staff_sql)) {
            $staff_stmt->bind_param("i", $job_id);
            $staff_stmt->execute();
            $staff_result = $staff_stmt->get_result();
            while ($staff_row = $staff_result->fetch_assoc()) {
                $staff[] = $staff_row;
            }
            $staff_stmt->close();
        }

        // Fetch additional costs for the current invoice
        $additional_costs = [];
        if ($invoice_id) {
            $additional_costs_sql = "SELECT description, amount FROM invoice_additional_costs WHERE invoice_id = ?";
            if ($additional_costs_stmt = $conn->prepare($additional_costs_sql)) {
                $additional_costs_stmt->bind_param("i", $invoice_id);
                $additional_costs_stmt->execute();
                $additional_costs_result = $additional_costs_stmt->get_result();
                while ($cost_row = $additional_costs_result->fetch_assoc()) {
                    $additional_costs[] = $cost_row;
                }
                $additional_costs_stmt->close();
            }
        }

        $jobs[] = [
            'customer_name' => $row['customer_name'],
            'car_plate_no' => $row['vehicle_plate'],
            'job_status' => $row['job_status'],
            'job_progress' => 'Completed',
            'job_date' => $row['job_date'],
            'total_cost' => $row['total_cost'] ?? 0,
            'payment_amount' => $row['payment_amount'] ?? 0,
            'payment_change' => $row['payment_change'] ?? 0,
            'services' => $services,
            'staff' => $staff,
            'additional_costs' => $additional_costs,
        ];
    }

    if (empty($jobs)) {
        echo json_encode(['success' => true, 'jobs' => [], 'error' => 'No transaction history available.']);
    } else {
        echo json_encode(['success' => true, 'jobs' => $jobs]);
    }
    
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database query failed.']);
}

$conn->close();
?>