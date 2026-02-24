<?php
// Start session and get user data
session_start();

// Require the database connection and audit log function
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
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

// Check user role - only admin and manager can delete services
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden: Insufficient permissions. Only admin and manager can delete services.'
    ]);
    exit();
}

// Allow both DELETE and POST methods for flexibility
if ($_SERVER['REQUEST_METHOD'] != 'DELETE' && $_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use DELETE or POST.'
    ]);
    exit();
}

// Get service ID from query string or request body
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // For DELETE requests, get ID from query string
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Service ID is required and must be numeric in query string'
        ]);
        exit();
    }
    $id = (int)$_GET['id'];
} else {
    // For POST requests, get ID from JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON data'
        ]);
        exit();
    }
    
    if (!isset($input['id']) || !is_numeric($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Service ID is required and must be numeric'
        ]);
        exit();
    }
    $id = (int)$input['id'];
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

// Get archive reason if provided
$archive_reason = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $archive_reason = isset($input['archive_reason']) ? trim($input['archive_reason']) : 'Manual deletion by user';
} else {
    $archive_reason = isset($_GET['reason']) ? trim($_GET['reason']) : 'Manual deletion by user';
}

try {
    // Begin transaction for atomic operations
    $conn->begin_transaction();
    
    // First, get the current service data for archiving
    $select_sql = "SELECT * FROM services WHERE id = ?";
    $select_stmt = $conn->prepare($select_sql);
    $select_stmt->bind_param("i", $id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Service not found'
        ]);
        $select_stmt->close();
        exit();
    }
    
    $service_data = $result->fetch_assoc();
    $select_stmt->close();
    
    // Insert into services_archive table
    $archive_sql = "INSERT INTO services_archive (
        id,
        name,
        min_cost,
        max_cost,
        min_hours,
        max_hours,
        description,
        original_created_at,
        archived_reason,
        archived_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $archive_stmt = $conn->prepare($archive_sql);
    
    if (!$archive_stmt) {
        throw new Exception("Failed to prepare archive statement: " . $conn->error);
    }
    
    $archive_stmt->bind_param(
        "isddddsssi",
        $service_data['id'],
        $service_data['name'],
        $service_data['min_cost'],
        $service_data['max_cost'],
        $service_data['min_hours'],
        $service_data['max_hours'],
        $service_data['description'],
        $service_data['created_at'],
        $archive_reason,
        $staff_id
    );
    
    $archive_success = $archive_stmt->execute();
    $archive_stmt->close();
    
    if (!$archive_success) {
        throw new Exception("Failed to archive service: " . $conn->error);
    }
    
    // Now delete from the main services table
    $delete_sql = "DELETE FROM services WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $id);
    $delete_success = $delete_stmt->execute();
    $delete_stmt->close();
    
    if (!$delete_success) {
        throw new Exception("Failed to delete service: " . $conn->error);
    }
    
    // Insert audit log
    $audit_success = insert_audit_log(
        $staff_id,
        'archive',
        'Archived service: ' . $service_data['name'],
        'services',
        $id,
        $service_data, // Old data
        null, // No new data for delete
        'Service archived to services_archive. Reason: ' . $archive_reason
    );
    
    if (!$audit_success) {
        // Log this error but don't fail the main operation
        error_log("Failed to insert audit log for service archive: " . $id);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Service archived successfully',
        'archived_service' => [
            'id' => $service_data['id'],
            'name' => $service_data['name'],
            'archived_at' => date('Y-m-d H:i:s'),
            'archived_reason' => $archive_reason
        ],
        'audit_logged' => $audit_success,
        'archived_by' => [
            'staff_id' => $staff_id,
            'username' => $_SESSION['username'] ?? '',
            'role' => $user_role
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
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