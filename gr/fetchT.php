<?php
header('Content-Type: application/json');

require_once '../dbconn.php';

$report_folder = 'reports/'; 

// --- 2. Get Input Data ---
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['report_type']) || !isset($input['start_date']) || !isset($input['end_date']) || !isset($input['file_name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters.']);
    exit;
}

$report_type = $input['report_type'];
$start_date = $input['start_date'];
$end_date = $input['end_date'];
$file_name = $input['file_name'];
$full_path = $report_folder . $file_name;

// --- 3. Check if file already exists ---
if (file_exists($full_path)) {
    echo json_encode(['success' => true, 'should_create' => false, 'message' => 'File already exists.']);
    exit;
}

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// SQL Query for Transaction Report
$data = [];
if ($report_type === 'transactions') {
    $sql = "
        SELECT 
            i.invoice_number, 
            j.customer_name, 
            j.vehicle_brand, 
            j.vehicle_plate,
            p.payment_date,
            i.total_amount
        FROM invoices i
        JOIN payments p ON i.id = p.invoice_id
        JOIN jobs j ON i.job_id = j.id
        WHERE DATE(p.payment_date) >= ? AND DATE(p.payment_date) <= ?
        GROUP BY i.id
        ORDER BY p.payment_date ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $stmt->close();
}

$conn->close();

echo json_encode(['success' => true, 'should_create' => true, 'data' => $data]);
?>