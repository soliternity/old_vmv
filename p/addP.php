<?php
// addP.php

header('Content-Type: application/json');
require_once '../dbconn.php';

$response = [
    'success' => false,
    'id' => null,
    'message' => ''
];

// Read JSON data from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['name'], $data['brand'], $data['cost'], $data['category'])) {
    $response['message'] = 'Missing required fields.';
    echo json_encode($response);
    exit;
}

// Sanitize inputs
$name = trim($data['name']);
$brand = trim($data['brand']);
$cost = (float)$data['cost']; // Keep cost as float for safety, though DDL specifies INTEGER
$category = trim($data['category']);

try {
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "INSERT INTO parts (name, brand, cost, category) VALUES (?, ?, ?, ?)";
    
    // Prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters: s=string, s=string, d=double/float, s=string
    $stmt->bind_param("ssds", $name, $brand, $cost, $category);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['id'] = $conn->insert_id;
        $response['message'] = "Part added successfully.";
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