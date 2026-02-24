<?php
// Start session and get user data
session_start();

// Require the database connection and audit log function
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
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

// Check user role - only admin and manager can update services
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden: Insufficient permissions. Only admin and manager can update services.'
    ]);
    exit();
}

// Only allow PUT or POST requests
if ($_SERVER['REQUEST_METHOD'] != 'PUT' && $_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use PUT or POST.'
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
if (!isset($input['id']) || !is_numeric($input['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Service ID is required and must be numeric'
    ]);
    exit();
}

// Use session user_id for audit logging instead of requiring it in input
$staff_id = $_SESSION['user_id'] ?? null;
if (!$staff_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'User session invalid. Please login again.'
    ]);
    exit();
}

$id = (int)$input['id'];
$name = isset($input['name']) ? trim($input['name']) : null;
$min_cost = isset($input['min_cost']) ? (float)$input['min_cost'] : null;
$max_cost = isset($input['max_cost']) ? (float)$input['max_cost'] : null;
$min_hours = isset($input['min_hours']) ? (float)$input['min_hours'] : null;
$max_hours = isset($input['max_hours']) ? (float)$input['max_hours'] : null;
$description = isset($input['description']) ? trim($input['description']) : null;

// Validate data ranges
if ($min_cost !== null && $max_cost !== null && $min_cost > $max_cost) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Minimum cost cannot be greater than maximum cost'
    ]);
    exit();
}

if ($min_hours !== null && $max_hours !== null && $min_hours > $max_hours) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Minimum hours cannot be greater than maximum hours'
    ]);
    exit();
}

try {
    // First, get the current data for audit logging
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
        exit();
    }
    
    $old_data = $result->fetch_assoc();
    $select_stmt->close();
    
    // Build dynamic UPDATE query based on provided fields
    $update_fields = [];
    $params = [];
    $types = '';
    
    if ($name !== null) {
        $update_fields[] = 'name = ?';
        $params[] = $name;
        $types .= 's';
    }
    
    if ($min_cost !== null) {
        $update_fields[] = 'min_cost = ?';
        $params[] = $min_cost;
        $types .= 'd';
    }
    
    if ($max_cost !== null) {
        $update_fields[] = 'max_cost = ?';
        $params[] = $max_cost;
        $types .= 'd';
    }
    
    if ($min_hours !== null) {
        $update_fields[] = 'min_hours = ?';
        $params[] = $min_hours;
        $types .= 'd';
    }
    
    if ($max_hours !== null) {
        $update_fields[] = 'max_hours = ?';
        $params[] = $max_hours;
        $types .= 'd';
    }
    
    if ($description !== null) {
        $update_fields[] = 'description = ?';
        $params[] = $description;
        $types .= 's';
    }
    
    // Add updated_at timestamp
    $update_fields[] = 'updated_at = CURRENT_TIMESTAMP';
    
    // Check if there are fields to update
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'No fields to update'
        ]);
        exit();
    }
    
    // Prepare SQL query
    $sql = "UPDATE services SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $params[] = $id;
    $types .= 'i';
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);
    
    // Execute update
    $success = $stmt->execute();
    
    if ($success && $stmt->affected_rows > 0) {
        // Fetch the updated record for audit log and response
        $select_sql = "SELECT * FROM services WHERE id = ?";
        $select_stmt = $conn->prepare($select_sql);
        $select_stmt->bind_param("i", $id);
        $select_stmt->execute();
        $result = $select_stmt->get_result();
        $new_data = $result->fetch_assoc();
        $select_stmt->close();
        
        // Prepare changed fields for audit log description
        $changed_fields = [];
        if ($name !== null && $old_data['name'] !== $name) {
            $changed_fields[] = "name: '{$old_data['name']}' → '{$name}'";
        }
        if ($min_cost !== null && $old_data['min_cost'] != $min_cost) {
            $changed_fields[] = "min cost: {$old_data['min_cost']} → {$min_cost}";
        }
        if ($max_cost !== null && $old_data['max_cost'] != $max_cost) {
            $changed_fields[] = "max cost: {$old_data['max_cost']} → {$max_cost}";
        }
        if ($min_hours !== null && $old_data['min_hours'] != $min_hours) {
            $changed_fields[] = "min hours: {$old_data['min_hours']} → {$min_hours}";
        }
        if ($max_hours !== null && $old_data['max_hours'] != $max_hours) {
            $changed_fields[] = "max hours: {$old_data['max_hours']} → {$max_hours}";
        }
        if ($description !== null && $old_data['description'] !== $description) {
            $changed_fields[] = "description updated";
        }
        
        $description = !empty($changed_fields) ? "Changed: " . implode(', ', $changed_fields) : "No changes detected";
        
        // Insert audit log
        $audit_success = insert_audit_log(
            $staff_id,
            'update',
            'Updated service: ' . ($name !== null ? $name : $old_data['name']),
            'services',
            $id,
            $old_data,
            $new_data,
            $description
        );
        
        if (!$audit_success) {
            // Log this error but don't fail the main operation
            error_log("Failed to insert audit log for service update: " . $id);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Service updated successfully',
            'data' => $new_data,
            'audit_logged' => $audit_success,
            'affected_rows' => $stmt->affected_rows,
            'updated_by' => [
                'staff_id' => $staff_id,
                'username' => $_SESSION['username'] ?? '',
                'role' => $user_role
            ]
        ]);
    } elseif ($success && $stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Service not found or no changes made'
        ]);
    } else {
        throw new Exception("Update failed: " . $stmt->error);
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