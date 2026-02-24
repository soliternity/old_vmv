<?php
// adm_data_fetch.php

header('Content-Type: application/json');

// --- 1. Database Connection ---
// Assuming '../dbconn.php' establishes a MySQLi connection in a variable named $conn
require_once '../dbconn.php'; 

// Check connection
if ($conn->connect_error) {
    // If connection fails, return an error message
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// --- 2. Initialize Data Structure ---
$response_data = [
    'stats' => [
        'vmv_users' => 0,
        'staff_accounts' => 0,
        'other' => [] 
    ],
    'activity_logs' => [],
    'login_logs' => []
];

// --- 3. Fetch Statistics ---

// 3.1. VMV Users (appusers)
$query_vmv = "SELECT COUNT(id) AS total_vmv_users FROM appusers";
$result_vmv = $conn->query($query_vmv);
if ($result_vmv && $row = $result_vmv->fetch_assoc()) {
    $response_data['stats']['vmv_users'] = (int)$row['total_vmv_users'];
}

// 3.2. Staff Accounts (staff - active and not archived)
$query_staff = "SELECT COUNT(id) AS total_staff FROM staff WHERE is_archived = FALSE AND status = 'activated'";
$result_staff = $conn->query($query_staff);
if ($result_staff && $row = $result_staff->fetch_assoc()) {
    $response_data['stats']['staff_accounts'] = (int)$row['total_staff'];
}

// 3.3. Services List (services)
$query_services = "SELECT COUNT(id) AS total_services FROM services";
$result_services = $conn->query($query_services);
if ($result_services && $row = $result_services->fetch_assoc()) {
    $response_data['stats']['other'][] = [
        "label" => "Services List", 
        "value" => (int)$row['total_services'],
        // Note: icon and link are handled by admdsh.js
    ];
}


// --- 4. Fetch System Activity Logs (audit_logs) ---
$query_activity = "
    SELECT 
        title, 
        action_type AS action, 
        staff_id, 
        table_name, 
        record_id, 
        created_at AS timestamp 
    FROM audit_logs 
    ORDER BY created_at DESC 
    LIMIT 12
"; 
$result_activity = $conn->query($query_activity);

if ($result_activity && $result_activity->num_rows > 0) {
    while($row = $result_activity->fetch_assoc()) {
        $response_data['activity_logs'][] = $row;
    }
}


// --- 5. Fetch Login Logs (login_logs) ---
$query_login = "
    SELECT 
        username, 
        staff_id, 
        status, 
        failure_reason AS reason, 
        login_time AS timestamp 
    FROM login_logs 
    ORDER BY login_time DESC 
    LIMIT 12
"; 
$result_login = $conn->query($query_login);

if ($result_login && $result_login->num_rows > 0) {
    while($row = $result_login->fetch_assoc()) {
        // Ensure status is capitalised for CSS class matching in JS
        $row['status'] = ucfirst($row['status']); 
        $response_data['login_logs'][] = $row;
    }
}


// --- 6. Close Connection and Output JSON ---
$conn->close();

echo json_encode($response_data, JSON_PRETTY_PRINT);
?>