<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Check if it's a preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. Only POST requests are accepted."
    ]);
    exit();
}

// Include database connection and audit logs function
require_once '../dbconn.php';
require_once '../adtLogs.php'; // Make sure the path is correct

// Check if user is logged in and has permission
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Unauthorized: Please log in first"
    ]);
    exit();
}

$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_role = $_SESSION['role'] ?? null;
if (!$current_user_id || !$current_user_role) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied: User role or ID not found"
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input (do NOT trust staff_id from client)
if (!$input || !isset($input['id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid input. Required fields: id, status"
    ]);
    exit();
}

$id = intval($input['id']);
$status = trim($input['status']);

// Validate status
$allowed_statuses = ['activated', 'deactivated'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid status. Allowed values: activated, deactivated"
    ]);
    exit();
}

try {
    // Check if staff exists and get current data for audit log
    $checkStmt = $conn->prepare("SELECT id, lname, fname, mname, username, email, role, status, is_archived, created_at, updated_at FROM staff WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Staff member not found with ID: $id"
        ]);
        exit();
    }
    
    $currentStaff = $checkResult->fetch_assoc();
    $oldStatus = $currentStaff['status'];
    $checkStmt->close();

    // Permission checks
    if ($id === $current_user_id) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You cannot change your own status."
        ]);
        exit();
    }
    if ($current_user_role === 'admin') {
        // Admin can update anyone
    } elseif ($current_user_role === 'manager') {
        // Manager can only update mechanic/cashier
        if (!in_array($currentStaff['role'], ['mechanic', 'cashier'])) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Managers can only update status of mechanics or cashiers."
            ]);
            exit();
        }
    } else {
        // Other roles cannot update anyone
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You do not have permission to update staff status."
        ]);
        exit();
    }

    // Prepare update statement
    $stmt = $conn->prepare("UPDATE staff SET status = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("si", $status, $id);

    // Execute update
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Get updated record
            $selectStmt = $conn->prepare("SELECT id, lname, fname, mname, username, email, role, status, is_archived, created_at, updated_at FROM staff WHERE id = ?");
            $selectStmt->bind_param("i", $id);
            $selectStmt->execute();
            $result = $selectStmt->get_result();
            $updatedStaff = $result->fetch_assoc();
            $selectStmt->close();
            
            // Create audit log entry
            $actionTitle = "Staff status changed from '$oldStatus' to '$status'";
            $logSuccess = insert_audit_log(
                $current_user_id, // Use session user ID
                'update', 
                $actionTitle,
                'staff', 
                $id, 
                ['status' => $oldStatus], // Old data
                ['status' => $status],    // New data
                "Staff member: {$currentStaff['fname']} {$currentStaff['lname']} (ID: $id)"
            );
            
            if (!$logSuccess) {
                // Log the audit log failure but don't fail the main operation
                error_log("Failed to create audit log for staff status update. Staff ID: $id, Performed by: $current_user_id");
            }

            echo json_encode([
                "success" => true,
                "message" => "Status updated successfully",
                "data" => $updatedStaff,
                "audit_logged" => $logSuccess
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "No changes made. Status was already '$status'",
                "data" => null
            ]);
        }
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>