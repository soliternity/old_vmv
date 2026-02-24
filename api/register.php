<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Get the JSON input
$data = json_decode(file_get_contents("php://input"));

// Validate input
if (!isset($data->lname, $data->fname, $data->email, $data->password) || empty($data->lname) || empty($data->fname) || empty($data->email) || empty($data->password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required."]);
    exit();
}

$lname = trim($data->lname);
$fname = trim($data->fname);
$mname = isset($data->mname) ? trim($data->mname) : NULL;
$email = trim($data->email);
$password = trim($data->password);

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

require_once '../dbconn.php';

// Check if email or username exists (only email now as username is removed)
$stmt = $conn->prepare("SELECT COUNT(*) FROM appusers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emailCount);
$stmt->fetch();
$stmt->close();

// Prepare response
$response = ["status" => "error", "message" => ""];
if ($emailCount > 0) {
    $response['message'] = "Email already exists.";
} else {
    // Prepare and bind for insertion
    $stmt = $conn->prepare("INSERT INTO appusers (lname, fname, mname, email, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $lname, $fname, $mname, $email, $hashedPassword);

    // Execute the statement
    if ($stmt->execute()) {
        $response = ["status" => "success", "message" => "User registered successfully."];
    } else {
        $response['message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Close connection
$conn->close();
echo json_encode($response);
?>