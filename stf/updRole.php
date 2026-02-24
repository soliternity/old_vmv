<?php
// updRole.php

// 1. 💡 CRITICAL: Disable error display to prevent HTML warnings from breaking JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
// This prevents errors from being outputted to the client, ensuring a clean JSON response.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 2. REQUIRE: This loads dbconn.php, which defines the global $conn variable.
require_once '../dbconn.php';

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Check for direct connection error from dbconn.php
// Note: dbconn.php uses die(), so this check is mostly defensive
if (empty($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error: Database connection failed.']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$staff_id = $data['id'] ?? null;
$new_role = $data['role'] ?? null;

// Basic input validation
if (!is_numeric($staff_id) || $staff_id <= 0 || empty($new_role)) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID or role provided.']);
    exit;
}

// Sanitize and validate role (match roles in stf.html select)
$valid_roles = ['admin', 'manager', 'mechanic', 'cashier'];
$new_role = strtolower($new_role);

if (!in_array($new_role, $valid_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
    exit;
}

// 3. CHANGE: $conn is now used directly, no function call needed.
try {
    // 1. Prepare SQL statement for updating the role
    $stmt = $conn->prepare("UPDATE staff SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $new_role, $staff_id);

    // 2. Execute the statement
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Staff role updated successfully.']);
        } else {
            // This happens if the staff ID exists, but the role value is already the new role.
            echo json_encode(['success' => true, 'message' => 'Staff role is already set to ' . $new_role . '.']);
        }
    } else {
        throw new Exception($stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
// Note: It is best practice to omit the closing ?>