<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: origin, content-type, accept, x-requested-with');

require_once '../dbconn.php';

$sql = "SELECT id, fname, lname FROM staff WHERE role = 'mechanic' AND status = 'activated'";
$result = $conn->query($sql);

$mechanics = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['name'] = $row['fname'] . ' ' . $row['lname'];
        unset($row['fname']);
        unset($row['lname']);
        $mechanics[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $mechanics]);
} else {
    echo json_encode(["status" => "success", "message" => "No mechanics found.", "data" => []]);
}

$conn->close();