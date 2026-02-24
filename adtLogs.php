<?php
require_once 'dbconn.php';
date_default_timezone_set('Asia/Manila');
/**
 * Insert audit log entry
 * 
 * @param int $staff_id ID of the staff member
 * @param string $action_type Type of action ('create', 'update', 'archive')
 * @param string $title Title/description of the action
 * @param string $table_name Name of the database table
 * @param int $record_id ID of the record
 * @param mixed $old_data Old data (optional)
 * @param mixed $new_data New data (optional)
 * @param string $description Additional description (optional)
 * @return bool True on success, False on failure
 */
function insert_audit_log($staff_id, $action_type, $title, $table_name, $record_id, $old_data = null, $new_data = null, $description = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (staff_id, action_type, title, table_name, record_id, old_data, new_data, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        return false;
    }
    
    // Convert data to JSON if provided
    $old_data_json = $old_data ? json_encode($old_data) : null;
    $new_data_json = $new_data ? json_encode($new_data) : null;
    
    $stmt->bind_param("isssisss", $staff_id, $action_type, $title, $table_name, $record_id, $old_data_json, $new_data_json, $description);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

// Example usage:
// insert_audit_log(1, 'update', 'Updated user profile', 'users', 15, $oldUserData, $newUserData, 'Changed email address');
?>