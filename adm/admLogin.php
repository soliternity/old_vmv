<?php
header('Content-Type: application/json');
require_once '../dbconn.php';
require_once '../lgnLogs.php';

// Start session
session_start();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if request is POST and has required fields
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$username = trim($input['username']);
$password = trim($input['password']);

// Check for empty fields
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

// Check database connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Query the database
$sql = "SELECT id, lname, fname, mname, username, email, password, role, status FROM staff WHERE username = ? AND is_archived = FALSE";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found
    log_login_attempt($conn, 0, $username, 'failed', 'User not found');
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit();
}

$user = $result->fetch_assoc();

// Check if account is activated
if ($user['status'] !== 'activated') {
    log_login_attempt($conn, $user['id'], $username, 'failed', 'Account deactivated');
    echo json_encode(['success' => false, 'message' => 'Account deactivated. Please contact administrator.']);
    exit();
}

// Check if user is admin
if ($user['role'] !== 'admin') {
    log_login_attempt($conn, $user['id'], $username, 'failed', 'Insufficient privileges - Not admin');
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit();
}

// Verify password
if (password_verify($password, $user['password'])) {
    // Successful login - Store session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['fname'] = $user['fname'];
    $_SESSION['lname'] = $user['lname'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Log successful attempt
    log_login_attempt($conn, $user['id'], $username, 'success');
    
    // Return user data (without password)
    $user_data = [
        'id' => $user['id'],
        'lname' => $user['lname'],
        'fname' => $user['fname'],
        'mname' => $user['mname'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status']
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Login successful',
        'data' => $user_data
    ]);
    
} else {
    // Wrong password
    log_login_attempt($conn, $user['id'], $username, 'failed', 'Incorrect password');
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}

$stmt->close();
$conn->close();
?>