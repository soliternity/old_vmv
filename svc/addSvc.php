<?php
// Start session and get user data
session_start();

// Require the database connection and audit log function
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized: Please login first'
    ]);
    exit();
}

// Check user role - only admin and manager can add services
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden: Insufficient permissions. Only admin and manager can add services.'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

// Get JSON input from request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON data'
    ]);
    exit();
}

// Check required fields
$required_fields = ['name', 'min_cost', 'max_cost', 'min_hours', 'max_hours'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Required field '$field' is missing or empty"
        ]);
        exit();
    }
}

// Extract and sanitize data
$name = trim($input['name']);
$min_cost = (float)$input['min_cost'];
$max_cost = (float)$input['max_cost'];
$min_hours = (float)$input['min_hours'];
$max_hours = (float)$input['max_hours'];
$description = isset($input['description']) ? trim($input['description']) : '';

// Validate data ranges
if ($min_cost > $max_cost) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Minimum cost cannot be greater than maximum cost'
    ]);
    exit();
}

if ($min_hours > $max_hours) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Minimum hours cannot be greater than maximum hours'
    ]);
    exit();
}

if ($min_cost <= 0 || $max_cost <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Cost values must be greater than 0'
    ]);
    exit();
}

if ($min_hours <= 0 || $max_hours <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Hour values must be greater than 0'
    ]);
    exit();
}

// Use session user_id for audit logging
$staff_id = $_SESSION['user_id'] ?? null;
if (!$staff_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'User session invalid. Please login again.'
    ]);
    exit();
}

try {
    // Check if service name already exists
    $check_sql = "SELECT id FROM services WHERE name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Service name already exists'
        ]);
        $check_stmt->close();
        exit();
    }
    $check_stmt->close();
    
    // Prepare INSERT query
    $sql = "INSERT INTO services (
        name, 
        min_cost, 
        max_cost, 
        min_hours, 
        max_hours, 
        description
    ) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("sdddds", $name, $min_cost, $max_cost, $min_hours, $max_hours, $description);
    
    // Execute insert
    $success = $stmt->execute();
    
    if ($success && $stmt->affected_rows > 0) {
        $new_id = $stmt->insert_id;
        
        // Fetch the newly created service
        $select_sql = "SELECT * FROM services WHERE id = ?";
        $select_stmt = $conn->prepare($select_sql);
        $select_stmt->bind_param("i", $new_id);
        $select_stmt->execute();
        $result = $select_stmt->get_result();
        $new_service = $result->fetch_assoc();
        $select_stmt->close();
        
        // Insert audit log
        $audit_success = insert_audit_log(
            $staff_id,
            'create',
            'Added new service: ' . $name,
            'services',
            $new_id,
            null, // No old data for create
            $new_service,
            'New service created with ID: ' . $new_id
        );
        
        if (!$audit_success) {
            // Log this error but don't fail the main operation
            error_log("Failed to insert audit log for new service: " . $name);
        }
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Service added successfully',
            'data' => $new_service,
            'audit_logged' => $audit_success,
            'inserted_id' => $new_id,
            'created_by' => [
                'staff_id' => $staff_id,
                'username' => $_SESSION['username'] ?? '',
                'role' => $user_role
            ]
        ]);
    } else {
        throw new Exception("Insert failed: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>