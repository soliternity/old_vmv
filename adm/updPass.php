<?php
header('Content-Type: application/json');

require_once '../dbconn.php';
require_once '../adtLogs.php';

$response = ['success' => false, 'message' => ''];

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Get and validate input
$staff_id = $_POST['staff_id'] ?? 0;
$new_password = $_POST['new_password'] ?? '';

if (empty($staff_id) || empty($new_password)) {
    $response['message'] = 'Staff ID and new password are required.';
    echo json_encode($response);
    exit;
}

// Sanitize inputs
$staff_id = (int)$staff_id;

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
if ($hashed_password === false) {
    $response['message'] = 'Password hashing failed.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction(); // Start a transaction

try {
    // 1. Update the password in the staff table
    $sql_staff = "UPDATE staff SET password = ? WHERE id = ?";
    $stmt_staff = $conn->prepare($sql_staff);
    if (!$stmt_staff) {
        throw new Exception('Staff update query failed: ' . $conn->error);
    }
    $stmt_staff->bind_param('si', $hashed_password, $staff_id);
    $stmt_staff->execute();

    if ($stmt_staff->affected_rows === 0) {
        throw new Exception('Password update failed or no changes were made.');
    }
    
    // 2. Mark the backup code as used
    $sql_backup = "UPDATE backup_codes SET is_used = TRUE, used_at = CURRENT_TIMESTAMP WHERE staff_id = ? AND is_used = FALSE ORDER BY created_at DESC LIMIT 1";
    $stmt_backup = $conn->prepare($sql_backup);
    if (!$stmt_backup) {
        throw new Exception('Backup code update query failed: ' . $conn->error);
    }
    $stmt_backup->bind_param('i', $staff_id);
    $stmt_backup->execute();

    if ($stmt_backup->affected_rows === 0) {
        throw new Exception('Backup code update failed. Code might have been used already.');
    }

    // 3. Log the password change in audit_logs
    $audit_log_data = json_encode(['old_password' => '[HASHED]', 'new_password' => '[HASHED]']); // Do not store actual passwords
    $description = 'Admin password reset';
    
    $sql_audit = "INSERT INTO audit_logs (staff_id, action_type, title, table_name, record_id, new_data, description) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_audit = $conn->prepare($sql_audit);
    if (!$stmt_audit) {
        throw new Exception('Audit log query failed: ' . $conn->error);
    }
    $action_type = 'update';
    $title = 'Password Reset';
    $table_name = 'staff';
    $stmt_audit->bind_param('issssss', $staff_id, $action_type, $title, $table_name, $staff_id, $audit_log_data, $description);
    $stmt_audit->execute();
    
    $conn->commit(); // Commit the transaction if all queries succeed
    
    $response['success'] = true;
    $response['message'] = 'Password updated successfully.';

} catch (Exception $e) {
    $conn->rollback(); // Rollback on any error
    $response['message'] = 'Transaction failed: ' . $e->getMessage();
} finally {
    if (isset($stmt_staff)) {
        $stmt_staff->close();
    }
    if (isset($stmt_backup)) {
        $stmt_backup->close();
    }
    if (isset($stmt_audit)) {
        $stmt_audit->close();
    }
    $conn->close();
}

echo json_encode($response);
?>