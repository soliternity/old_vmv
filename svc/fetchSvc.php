<?php
// Require the database connection
require_once '../dbconn.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Prepare and execute the SQL query
    $sql = "SELECT 
                id,
                name,
                min_cost,
                max_cost,
                min_hours,
                max_hours,
                description,
                created_at,
                updated_at
            FROM services 
            ORDER BY name ASC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $services = array();
    
    // Fetch all services
    while ($row = $result->fetch_assoc()) {
        $services[] = array(
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'min_cost' => (float)$row['min_cost'],
            'max_cost' => (float)$row['max_cost'],
            'min_hours' => (float)$row['min_hours'],
            'max_hours' => (float)$row['max_hours'],
            'description' => $row['description'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        );
    }
    
    $stmt->close();
    
    // Return success response with services data
    echo json_encode([
        'success' => true,
        'data' => $services,
        'count' => count($services),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} finally {
    // Close connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>