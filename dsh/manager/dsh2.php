<?php
// dsh.php - Dashboard Data Fetch Script (Manager View)

// 1. Include the database connection file. Assumes it sets up a $conn mysqli object.
require_once '../../dbconn.php'; 

// Set the header to indicate the response content type is JSON
header('Content-Type: application/json');

// Check connection
if ($conn->connect_error) {
    // Return an error message as JSON if connection fails
    echo json_encode(['error' => 'Database Connection failed: ' . $conn->connect_error]);
    exit();
}

// Initialize the data array that will be sent back to the client
$data = [];

// --- Date Calculations for SQL Queries (Procedural Style) ---
$current_date = date('Y-m-d');
$first_day_of_month = date('Y-m-01');
$first_day_of_last_month = date('Y-m-01', strtotime('first day of last month'));
$tomorrow_date = date('Y-m-d', strtotime('+1 day'));
$start_of_week = date('Y-m-d', strtotime('monday this week'));


// -----------------------------------------------------
// 1. Operational & Financial Summary
// -----------------------------------------------------

// A. Completed Invoices (This Month & Last Month)
$sql_invoices = "SELECT
    (SELECT COUNT(id) FROM invoices WHERE created_at >= '$first_day_of_month') AS completed_this_month,
    (SELECT COUNT(id) FROM invoices WHERE created_at >= '$first_day_of_last_month' AND created_at < '$first_day_of_month') AS completed_last_month";

$result_invoices = $conn->query($sql_invoices);
if ($result_invoices) {
    $row = $result_invoices->fetch_assoc();
    $data['completedInvoices'] = (int)$row['completed_this_month'];
    $data['lastMonthInvoices'] = (int)$row['completed_last_month'];
}


// B. Pending & Ongoing Job Orders
$sql_jobs = "SELECT
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pendingJobs,
    SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) AS ongoingJobs
FROM jobs
WHERE status IN ('Pending', 'Ongoing')";

$result_jobs = $conn->query($sql_jobs);
if ($result_jobs) {
    $row = $result_jobs->fetch_assoc();
    $data['pendingJobs'] = (int)$row['pendingJobs'];
    $data['ongoingJobs'] = (int)$row['ongoingJobs'];
}


// C. Appointments Summary (Confirmed)
$sql_appointments = "SELECT
    SUM(CASE WHEN date = '$current_date' AND status = 'confirmed' THEN 1 ELSE 0 END) AS todayAppointments,
    SUM(CASE WHEN date = '$tomorrow_date' AND status = 'confirmed' THEN 1 ELSE 0 END) AS tomorrowAppointments,
    SUM(CASE WHEN date >= '$start_of_week' AND date <= NOW() AND status = 'confirmed' THEN 1 ELSE 0 END) AS thisWeekAppointments
FROM appointment_schedule
WHERE status = 'confirmed' AND date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; // Optimizes the overall WHERE clause

$result_appointments = $conn->query($sql_appointments);
if ($result_appointments) {
    $row = $result_appointments->fetch_assoc();
    $data['todayAppointments'] = (int)$row['todayAppointments'];
    $data['tomorrowAppointments'] = (int)$row['tomorrowAppointments'];
    $data['thisWeekAppointments'] = (int)$row['thisWeekAppointments'];
}


// D. Active Chat Conversations (status = 'not_done')
$sql_chats = "SELECT COUNT(conversation_id) AS activeChats
FROM conversation
WHERE status = 'not_done'";

$result_chats = $conn->query($sql_chats);
if ($result_chats) {
    $row = $result_chats->fetch_assoc();
    $data['activeChats'] = (int)$row['activeChats'];
}


// -----------------------------------------------------
// 2. Personnel & Performance (Mechanic Ranking)
// -----------------------------------------------------

// Get top 3 mechanics by completed jobs in the last 30 days
$sql_mechanic_ranking = "SELECT
    s.fname,
    s.lname,
    COUNT(js.job_id) AS services_count
FROM job_service js
INNER JOIN jobs j ON js.job_id = j.id
INNER JOIN staff s ON js.completed_by = s.id
WHERE j.status = 'Paid' -- Only count completed jobs
    AND js.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) -- Completed in the last 30 days
    AND s.role = 'mechanic' -- Filter for mechanics only
GROUP BY s.id, s.fname, s.lname
ORDER BY services_count DESC
LIMIT 3"; // <-- CHANGED FROM 5 TO 3

$result_ranking = $conn->query($sql_mechanic_ranking);
$mechanicData = [];
if ($result_ranking) {
    while ($row = $result_ranking->fetch_assoc()) {
        $mechanicData[] = [
            'name' => $row['fname'] . ' ' . $row['lname'],
            'services' => (int)$row['services_count']
        ];
    }
    $data['mechanicData'] = $mechanicData;
}


// Close the connection
$conn->close();

// Output the final JSON data
echo json_encode($data);
?>