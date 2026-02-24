<?php
include '../dbconn.php';

// Get the POST data
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->au_id) || !isset($data->initial_description) || !isset($data->conclusion)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

$au_id = $conn->real_escape_string($data->au_id);
$initial_description = $conn->real_escape_string($data->initial_description);
$conclusion = $conn->real_escape_string($data->conclusion);

$sql = "INSERT INTO reports (au_id, initial_description, conclusion) VALUES ('$au_id', '$initial_description', '$conclusion')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "success", "message" => "New report created successfully.", "report_id" => $conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error: " . $sql . "<br>" . $conn->error]);
}

$conn->close();
?>