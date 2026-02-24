<?php
// Set the content type header to JSON
header('Content-Type: application/json');

require_once '../dbconn.php'; 

// Fetch all necessary part details
$sql = "SELECT id, name, brand, cost, category FROM parts ORDER BY name ASC";
$result = $conn->query($sql);

if ($result) {
    $parts = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure 'cost' is always treated as a floating-point number
        $row['cost'] = (float) $row['cost'];
        $parts[] = $row;
    }
    
    $response['status'] = 'success';
    $response['message'] = 'Parts fetched successfully.';
    $response['data'] = $parts;
    
    $result->free();
} else {
    $response['message'] = 'Database query failed: ' . $conn->error;
}

$conn->close();

echo json_encode($response);
?>