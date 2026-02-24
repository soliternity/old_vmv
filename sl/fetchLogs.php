<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../dbconn.php';
// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Connection failed: " . $conn->connect_error]);
    exit();
}

$log_type = $_GET['log_type'] ?? 'audit_logs';
$search_query = $_GET['search'] ?? '';

$sql = "";
$stmt = null;

if ($log_type === 'audit_logs') {
    $sql = "SELECT * FROM audit_logs";
    if (!empty($search_query)) {
        $sql .= " WHERE title LIKE ?";
        $search_param = "%" . $search_query . "%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $search_param);
    } else {
        $stmt = $conn->prepare($sql);
    }
} elseif ($log_type === 'login_logs') {
    $sql = "SELECT * FROM login_logs";
    if (!empty($search_query)) {
        $sql .= " WHERE username LIKE ? OR failure_reason LIKE ?";
        $search_param = "%" . $search_query . "%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $search_param, $search_param);
    } else {
        $stmt = $conn->prepare($sql);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid log type."]);
    exit();
}

if ($stmt) {
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Query execution failed: " . $stmt->error]);
        exit();
    }

    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    echo json_encode($logs);

    $stmt->close();
}

$conn->close();