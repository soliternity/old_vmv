<?php
// fetchP.php

header('Content-Type: application/json');
require_once '../dbconn.php'; // Path to your database connection file

$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

try {
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT id, name, brand, cost, category FROM parts ORDER BY id DESC";
    $result = $conn->query($sql);

    if ($result) {
        $parts = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure cost is an integer or float for JS
            $row['cost'] = (float)$row['cost']; 
            $parts[] = $row;
        }
        $response['success'] = true;
        $response['data'] = $parts;
        $response['message'] = "Parts fetched successfully.";
    } else {
        throw new Exception("Error executing query: " . $conn->error);
    }

    $conn->close();
} catch (Exception $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
?>