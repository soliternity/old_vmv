<?php
// adm_metrics.php

// 1. Configuration and Connection
// Assuming ../dbconn.php contains the database connection logic
require_once '../../dbconn.php';

// Set header to return JSON content
header('Content-Type: application/json');

// Initialize the response array
$metrics = [
    'job_order_count' => 0,
    'in_progress_jobs' => 0,
    'completed_jobs_today' => 0,
    'transactions_count_today' => 0,
    'appointments_this_month' => 0,
    'system_logs_24h' => 0,
    'vmv_users_count' => 0,
    'active_conversations' => 0,
];

// Check for database connection error
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// 2. SQL Queries to Fetch Metrics

// --- Metric 1: Job Order Count & Status Breakdown ---
$sql_jobs = "
    SELECT 
        COUNT(id) AS total_jobs,
        SUM(CASE WHEN status = 'Ongoing' OR status = 'Pending' THEN 1 ELSE 0 END) AS ongoing_jobs,
        SUM(CASE WHEN status = 'Paid' OR status = 'Completed' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS completed_today
    FROM jobs;
";
$result_jobs = $conn->query($sql_jobs);
if ($result_jobs && $row = $result_jobs->fetch_assoc()) {
    $metrics['job_order_count'] = (int) $row['total_jobs'];
    $metrics['in_progress_jobs'] = (int) $row['ongoing_jobs'];
    $metrics['completed_jobs_today'] = (int) $row['completed_today'];
}


// --- Metric 2: Transactions Count Today ---
$sql_payments = "
    SELECT 
        COUNT(id) AS transactions_today
    FROM payments 
    WHERE DATE(payment_date) = CURDATE();
";
$result_payments = $conn->query($sql_payments);
if ($result_payments && $row = $result_payments->fetch_assoc()) {
    $metrics['transactions_count_today'] = (int) $row['transactions_today'];
}


// --- Metric 3: Appointments (This Month) ---
$sql_appointments = "
    SELECT 
        COUNT(appointment_id) AS appointments_month
    FROM appointment_schedule 
    WHERE 
        YEAR(date) = YEAR(CURDATE()) 
        AND MONTH(date) = MONTH(CURDATE())
        AND status = 'confirmed';
";
$result_appointments = $conn->query($sql_appointments);
if ($result_appointments && $row = $result_appointments->fetch_assoc()) {
    $metrics['appointments_this_month'] = (int) $row['appointments_month'];
}


// --- Metric 4: System Logs (24h) - using audit_logs table ---
$sql_logs = "
    SELECT 
        COUNT(id) AS logs_24h
    FROM audit_logs 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
";
$result_logs = $conn->query($sql_logs);
if ($result_logs && $row = $result_logs->fetch_assoc()) {
    $metrics['system_logs_24h'] = (int) $row['logs_24h'];
}


// --- Metric 5: VMV Users Count (assuming 'appusers' table are VMV users) ---
$sql_users = "
    SELECT 
        COUNT(id) AS total_users
    FROM appusers;
";
$result_users = $conn->query($sql_users);
if ($result_users && $row = $result_users->fetch_assoc()) {
    $metrics['vmv_users_count'] = (int) $row['total_users'];
}


// --- Metric 6: Active Conversations ---
// Assuming 'active' means status is 'not_done'
$sql_chats = "
    SELECT 
        COUNT(conversation_id) AS active_chats
    FROM conversation 
    WHERE status = 'not_done';
";
$result_chats = $conn->query($sql_chats);
if ($result_chats && $row = $result_chats->fetch_assoc()) {
    $metrics['active_conversations'] = (int) $row['active_chats'];
}


// 3. Output and Close Connection
$conn->close();

echo json_encode($metrics);
?>