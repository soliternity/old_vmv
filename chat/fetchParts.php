<?php
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
session_start();

require_once '../dbconn.php';

// Security check: Ensure staff is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

// Get the search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    // Return empty array if no query is provided
    echo json_encode([]);
    exit();
}

// Search for parts matching the query (name or brand)
// Columns used: id, name, brand, cost, category
$search_param = "%" . $query . "%";
$sql = "SELECT id, name, brand, cost, category 
        FROM parts 
        WHERE name LIKE ? OR brand LIKE ? 
        LIMIT 10"; 

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $search_param, $search_param);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$parts = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Mapped database columns to expected JavaScript fields:
    // 'cost' -> 'price'
    // 'brand' + 'category' -> 'description'
    // Placeholder -> 'stock'
    $parts[] = [
        'id' => intval($row['id']),
        'name' => htmlspecialchars($row['name']),
        'description' => htmlspecialchars("Brand: " . $row['brand'] . " | Category: " . $row['category']),
        'price' => number_format($row['cost'], 2, '.', ''),
        'stock' => 'In Stock', // Placeholder as 'stock' column is not in parts.sql
    ];
}

echo json_encode($parts);

mysqli_close($conn);
?>