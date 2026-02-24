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

// Include database connection and audit logs function
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input (do NOT trust archived_by from client)
if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid input. Required field: id"
    ]);
    exit();
}

$id = intval($input['id']);

// Prevent self-archiving
if ($id === $current_user_id) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Cannot archive your own account"
    ]);
    exit();
}

try {
    $conn->begin_transaction();

    // Check if staff exists and get current data
    $checkStmt = $conn->prepare("SELECT * FROM staff WHERE id = ? AND is_archived = FALSE");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Staff member not found or already archived with ID: $id"
        ]);
        exit();
    }
    
    $staffData = $checkResult->fetch_assoc();
    $checkStmt->close();

    // Permission checks
    if ($current_user_role === 'admin') {
        // Admin can archive anyone
    } elseif ($current_user_role === 'manager') {
        // Manager can only archive mechanic/cashier
        if (!in_array($staffData['role'], ['mechanic', 'cashier'])) {
            http_response_code(403);
            echo json_encode([
                "success" => false,
                "message" => "Managers can only archive mechanics or cashiers."
            ]);
            exit();
        }
    } else {
        // Other roles cannot archive anyone
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You do not have permission to archive staff."
        ]);
        exit();
    }

    // Insert into archive table
    $archiveStmt = $conn->prepare("
        INSERT INTO staff_archive 
        (id, lname, fname, mname, username, email, password, role, status, original_created_at, original_updated_at, archived_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $archiveStmt->bind_param(
        "issssssssssi",
        $staffData['id'],
        $staffData['lname'],
        $staffData['fname'],
        $staffData['mname'],
        $staffData['username'],
        $staffData['email'],
        $staffData['password'],
        $staffData['role'],
        $staffData['status'],
        $staffData['created_at'],
        $staffData['updated_at'],
        $current_user_id
    );

    if (!$archiveStmt->execute()) {
        throw new Exception("Failed to archive staff: " . $archiveStmt->error);
    }
    $archiveStmt->close();

    // Mark as archived in main table
    $updateStmt = $conn->prepare("UPDATE staff SET is_archived = TRUE, status = 'deactivated', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $id);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to mark staff as archived: " . $updateStmt->error);
    }
    $updateStmt->close();

    // Create audit log entry
    $actionTitle = "Staff member archived";
    $logSuccess = insert_audit_log(
        $current_user_id,
        'archive', 
        $actionTitle,
        'staff', 
        $id, 
        $staffData, // Full old data
        null,       // No new data since it's an archive
        "Archived staff: {$staffData['fname']} {$staffData['lname']} (ID: $id). Archived by: {$current_user_role} (ID: $current_user_id)"
    );

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Staff member archived successfully",
        "data" => [
            "archived_id" => $id,
            "staff_name" => "{$staffData['fname']} {$staffData['lname']}",
            "archived_by" => $current_user_id,
            "archived_at" => date('Y-m-d H:i:s'),
            "audit_logged" => $logSuccess
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
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