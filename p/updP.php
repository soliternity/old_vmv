<?php
// updP.php

header('Content-Type: application/json');
require_once '../dbconn.php';

$response = [
    'success' => false,
    'message' => ''
];

// Read JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'], $data['name'], $data['brand'], $data['cost'], $data['category'])) {
    $response['message'] = 'Missing required fields.';
    echo json_encode($response);
    exit;
}

// Sanitize inputs
$id = (int)$data['id'];
$name = trim($data['name']);
$brand = trim($data['brand']);
$cost = (float)$data['cost']; // Keep cost as float
$category = trim($data['category']);

try {
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "UPDATE parts SET name = ?, brand = ?, cost = ?, category = ? WHERE id = ?";
    
    // Prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters: s=string, s=string, d=double/float, s=string, i=integer
    $stmt->bind_param("ssdsi", $name, $brand, $cost, $category, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Part updated successfully.";
        } else {
            // This happens if the part exists but no data was changed
            $response['success'] = true; 
            $response['message'] = "Part updated, or no changes detected.";
        }
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $response['message'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
?>