<?php
// dsh.php - Script to fetch ALL Cashier Dashboard data from MySQLi

// 1. Database Connection
// Use the provided path to connect file
include '../../dbconn.php';

// Check for database connection error
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Connection Failed: ' . $conn->connect_error]);
    exit();
}

// 2. Set Header for JSON Output
header('Content-Type: application/json');

// Initialize the final data array
$dashboard_data = [
    // Summary Data
    'billingToProcess' => 0,
    'appointmentsToday' => 0,
    'jobOrders' => [
        'total' => 0,
        'pending' => 0,
        'ongoing' => 0,
        'completed' => 0,
    ],
    // Search Data
    'services' => [],
    'parts' => [],
    'error' => null
];

// Helper to manage errors
$errors = [];

// ----------------------------------------------------
// QUERY 1: Billing Invoices To Process (Invoices without Payments)
// ----------------------------------------------------
$sql_invoices = "
    SELECT COUNT(t1.id) AS count 
    FROM invoices t1 
    LEFT JOIN payments t2 ON t1.id = t2.invoice_id 
    WHERE t2.invoice_id IS NULL;
";
$result_invoices = $conn->query($sql_invoices);

if ($result_invoices) {
    $row = $result_invoices->fetch_assoc();
    $dashboard_data['billingToProcess'] = (int)$row['count'];
    $result_invoices->free();
} else {
    $errors[] = 'Invoice query failed: ' . $conn->error;
}


// ----------------------------------------------------
// QUERY 2: Appointments Today (Confirmed appointments for the current date)
// ----------------------------------------------------
$sql_appointments = "
    SELECT COUNT(appointment_id) AS count 
    FROM appointment_schedule 
    WHERE date = CURDATE() AND status = 'confirmed';
";
$result_appointments = $conn->query($sql_appointments);

if ($result_appointments) {
    $row = $result_appointments->fetch_assoc();
    $dashboard_data['appointmentsToday'] = (int)$row['count'];
    $result_appointments->free();
} else {
    $errors[] = 'Appointment query failed: ' . $conn->error;
}


// ----------------------------------------------------
// QUERY 3: Ongoing Job Orders (Total and Status breakdown)
// ----------------------------------------------------
$sql_jobs = "
    SELECT 
        COUNT(id) AS total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) AS ongoing,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
    FROM jobs;
";
$result_jobs = $conn->query($sql_jobs);

if ($result_jobs) {
    $row = $result_jobs->fetch_assoc();
    $dashboard_data['jobOrders']['total'] = (int)$row['total'];
    $dashboard_data['jobOrders']['pending'] = (int)$row['pending'];
    $dashboard_data['jobOrders']['ongoing'] = (int)$row['ongoing'];
    $dashboard_data['jobOrders']['completed'] = (int)$row['completed'];
    $result_jobs->free();
} else {
    $errors[] = 'Job order query failed: ' . $conn->error;
}

// ----------------------------------------------------
// QUERY 4: Services List (for search)
// ----------------------------------------------------
$sql_services = "
    SELECT id, name, min_cost, max_cost, min_hours, max_hours, description 
    FROM services
    ORDER BY name ASC;
";
$result_services = $conn->query($sql_services);

if ($result_services) {
    while ($row = $result_services->fetch_assoc()) {
        $dashboard_data['services'][] = [
            'id' => 'SVC-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT), // Format ID
            'name' => $row['name'],
            'price' => (float)$row['min_cost'], // Use min_cost as the primary display price
            'category' => 'Maintenance/Repair', // Default category
            'hourRange' => (float)$row['min_hours'] . ' - ' . (float)$row['max_hours'] . ' hours',
            'costRange' => '₱' . number_format($row['min_cost'], 2) . ' - ₱' . number_format($row['max_cost'], 2),
            'description' => $row['description'],
        ];
    }
    $result_services->free();
} else {
    $errors[] = 'Services query failed: ' . $conn->error;
}

// ----------------------------------------------------
// QUERY 5: Parts List (for search)
// ----------------------------------------------------
$sql_parts = "
    SELECT id, name, brand, cost, category 
    FROM parts
    ORDER BY name ASC;
";
$result_parts = $conn->query($sql_parts);

if ($result_parts) {
    while ($row = $result_parts->fetch_assoc()) {
        $dashboard_data['parts'][] = [
            'sku' => 'PT-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT), // Format SKU
            'name' => $row['name'] . ' (' . $row['brand'] . ')',
            'stock' => 0, // Placeholder
            'unitPrice' => (float)$row['cost'],
            'location' => 'N/A', // Placeholder
            'category' => $row['category'],
        ];
    }
    $result_parts->free();
} else {
    $errors[] = 'Parts query failed: ' . $conn->error;
}

// 3. Close Connection and Output JSON
$conn->close();

// Consolidate errors
if (!empty($errors)) {
    $dashboard_data['error'] = implode(' | ', $errors);
    http_response_code(500);
} else {
    unset($dashboard_data['error']);
}

echo json_encode($dashboard_data);
?>