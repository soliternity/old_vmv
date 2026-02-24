<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../dbconn.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->password)) {
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

$email = $data->email;
$password = $data->password;

$sql = "
    SELECT 
        u.id, 
        u.fname, 
        u.mname,
        u.lname,
        u.email, 
        u.password,
        c.id AS car_id
    FROM appusers u
    LEFT JOIN cars c ON u.id = c.appuser_id
    WHERE u.email = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password'])) {
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'fname' => $user['fname'],
        'mname' => $user['mname'],
        'lname' => $user['lname'],
        'email' => $user['email'],
        'car_id' => $user['car_id'] // This will be null if the user has no car
    ],
    'token' => 'example-token' // You should generate and return a real token here
]);

$stmt->close();
$conn->close();
?>