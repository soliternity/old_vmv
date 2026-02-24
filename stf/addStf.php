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

// Check if user has permission to add staff (admin or manager)
$current_user_role = $_SESSION['role'] ?? null;
if (!in_array($current_user_role, ['admin', 'manager'])) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied: Only admins and managers can add staff members"
    ]);
    exit();
}

// Include database connection and audit logs function
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON input"
    ]);
    exit();
}

// Required fields
$required_fields = ['lname', 'fname', 'username', 'email', 'password', 'role'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty(trim($input[$field]))) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Missing required field: $field"
        ]);
        exit();
    }
}

// Validate role based on current user's permissions
$allowed_roles = [];
if ($current_user_role === 'admin') {
    $allowed_roles = ['admin', 'manager', 'mechanic', 'cashier'];
} elseif ($current_user_role === 'manager') {
    $allowed_roles = ['mechanic', 'cashier'];
}

if (!in_array($input['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Access denied: Cannot create staff with role '{$input['role']}'"
    ]);
    exit();
}

// Sanitize input
$lname = trim($conn->real_escape_string($input['lname']));
$fname = trim($conn->real_escape_string($input['fname']));
$mname = isset($input['mname']) ? trim($conn->real_escape_string($input['mname'])) : null;
$username = trim($conn->real_escape_string($input['username']));
$email = trim($conn->real_escape_string($input['email']));
$password = password_hash($input['password'], PASSWORD_DEFAULT);
$role = trim($conn->real_escape_string($input['role']));
$status = isset($input['status']) ? trim($conn->real_escape_string($input['status'])) : 'activated';

// Validate status
if (!in_array($status, ['activated', 'deactivated'])) {
    $status = 'activated';
}

// Get current user ID for audit log
$current_user_id = $_SESSION['user_id'] ?? null;

try {
    // Check if username already exists
    $checkUsernameStmt = $conn->prepare("SELECT id FROM staff WHERE username = ? AND is_archived = FALSE");
    $checkUsernameStmt->bind_param("s", $username);
    $checkUsernameStmt->execute();
    $usernameResult = $checkUsernameStmt->get_result();
    
    if ($usernameResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Username already exists"
        ]);
        exit();
    }
    $checkUsernameStmt->close();

    // Check if email already exists
    $checkEmailStmt = $conn->prepare("SELECT id FROM staff WHERE email = ? AND is_archived = FALSE");
    $checkEmailStmt->bind_param("s", $email);
    $checkEmailStmt->execute();
    $emailResult = $checkEmailStmt->get_result();
    
    if ($emailResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);
        exit();
    }
    $checkEmailStmt->close();

    // Start transaction
    $conn->begin_transaction();

    // Insert new staff member
    $insertStmt = $conn->prepare("
        INSERT INTO staff (lname, fname, mname, username, email, password, role, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insertStmt->bind_param(
        "ssssssss",
        $lname,
        $fname,
        $mname,
        $username,
        $email,
        $password,
        $role,
        $status
    );

    if (!$insertStmt->execute()) {
        throw new Exception("Failed to insert staff: " . $insertStmt->error);
    }

    $new_staff_id = $conn->insert_id;
    $insertStmt->close();

    // Get the newly created staff data
    $selectStmt = $conn->prepare("
        SELECT id, lname, fname, mname, username, email, role, status, is_archived, created_at, updated_at 
        FROM staff 
        WHERE id = ?
    ");
    $selectStmt->bind_param("i", $new_staff_id);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $new_staff = $result->fetch_assoc();
    $selectStmt->close();

    // Create audit log entry
    $actionTitle = "New staff member created";
    $logSuccess = insert_audit_log(
        $current_user_id,
        'create', 
        $actionTitle,
        'staff', 
        $new_staff_id,
        null, // No old data
        $new_staff, // New data
        "Created by: {$_SESSION['username']} (ID: $current_user_id). Role: $role"
    );

    if (!$logSuccess) {
        error_log("Failed to create audit log for new staff member. Staff ID: $new_staff_id, Created by: $current_user_id");
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Staff member created successfully",
        "data" => $new_staff,
        "audit_logged" => $logSuccess
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
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