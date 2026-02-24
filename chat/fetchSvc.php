<?php
session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../dbconn.php'; // Path to your database connection file

// Basic staff authorization check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get search query from GET request
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search_query) || strlen($search_query) < 2) {
    echo json_encode([]); // Return empty array for short/empty query
    exit();
}

// Sanitize query for SQL LIKE search
$search_param = "%" . $search_query . "%";

// SQL to search for services by name or description
// Fields: name, min_cost, max_cost, min_hours, max_hours, description (from services.sql)
$sql = "SELECT name, min_cost, max_cost, min_hours, max_hours, description FROM services WHERE name LIKE ? OR description LIKE ? LIMIT 10";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database statement error: ' . mysqli_error($conn)]);
    exit();
}

// 'ss' for two string parameters
mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$services = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Format the numerical fields for consistent display
    $row['min_cost'] = number_format((float)$row['min_cost'], 2, '.', '');
    $row['max_cost'] = number_format((float)$row['max_cost'], 2, '.', '');
    $row['min_hours'] = number_format((float)$row['min_hours'], 2, '.', '');
    $row['max_hours'] = number_format((float)$row['max_hours'], 2, '.', '');
    $services[] = $row;
}

echo json_encode($services);

mysqli_stmt_close($stmt);
?>