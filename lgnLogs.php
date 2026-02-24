<?php
require_once 'dbconn.php';

function log_login_attempt($conn, $staff_id, $username, $status, $failure_reason = null) {
    $staff_id = (int)$staff_id;
    $username = $conn->real_escape_string(trim($username));
    $status = $conn->real_escape_string($status);
    $failure_reason = $failure_reason ? $conn->real_escape_string(trim($failure_reason)) : null;
    
    if ($failure_reason !== null) {
        $sql = "INSERT INTO login_logs (staff_id, username, status, failure_reason) VALUES ($staff_id, '$username', '$status', '$failure_reason')";
    } else {
        $sql = "INSERT INTO login_logs (staff_id, username, status) VALUES ($staff_id, '$username', '$status')";
    }
    
    return $conn->query($sql);
}
?>