<?php
header('Content-Type: application/json');
require_once '../dbconn.php'; // Use the specified connection file

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$data = [];
$sql = "SELECT id, fname, lname, mname FROM appusers";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $fullName = trim($row['fname'] . ' ' . $row['mname'] . ' ' . $row['lname']);
        // Store the ID and the full name for the JavaScript side to handle the datalist search.
        $data[] = [
            'id' => $row['id'],
            'name' => $fullName
        ];
    }
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
}

$conn->close();
?>