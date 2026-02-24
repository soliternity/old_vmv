<?php
// login.php

// Start the session at the very beginning of the script
session_start();

header('Content-Type: application/json');
require_once '../dbconn.php'; // Make sure this path is correct
require_once '../lgnLogs.php'; // Make sure this path is correct

// Prepare the response data structure
$response = ['success' => false, 'message' => 'An unknown error occurred.', 'staff' => null];

// Check if the request method is POST and if required fields are set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Check if the username is empty
    if (empty($username)) {
        $response['message'] = 'Username cannot be empty.';
        log_login_attempt($conn, 0, $username, 'failed', 'Empty username.');
        echo json_encode($response);
        exit;
    }
    
    // Check if the password is empty
    if (empty($password)) {
        $response['message'] = 'Password cannot be empty.';
        log_login_attempt($conn, 0, $username, 'failed', 'Empty password.');
        echo json_encode($response);
        exit;
    }

    // Use a prepared statement to prevent SQL injection
    // Fetch all necessary user data for the session
    $sql = "SELECT id, fname, lname, username, email, password, role, status FROM staff WHERE username = ? AND is_archived = 0";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $response['message'] = 'Failed to prepare the statement: ' . $conn->error;
        log_login_attempt($conn, 0, $username, 'failed', 'Database error.');
        echo json_encode($response);
        exit;
    }
    
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Block 'admin' role logins
        if ($user['role'] === 'admin') {
            $response['message'] = 'User not found.';
            log_login_attempt($conn, $user['id'], $username, 'failed', 'Admin login attempt.');
        } 
        // Check for 'deactivated' status
        else if ($user['status'] === 'deactivated') {
            $response['message'] = 'Account is deactivated. Please contact support.';
            log_login_attempt($conn, $user['id'], $username, 'failed', 'Deactivated account.');
        } 
        // Verify the password
        else if (password_verify($password, $user['password'])) {
            // A successful login. Store all necessary data in the session.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();

            $response['success'] = true;
            $response['message'] = 'Login successful!';
            $response['staff'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];
            log_login_attempt($conn, $user['id'], $username, 'success');
        } 
        // Incorrect password
        else {
            $response['message'] = 'Invalid username or password.';
            log_login_attempt($conn, $user['id'], $username, 'failed', 'Incorrect password.');
        }
    } 
    // User not found
    else {
        $response['message'] = 'Invalid username or password.';
        log_login_attempt($conn, 0, $username, 'failed', 'User not found.');
    }
    
    $stmt->close();
} else {
    // Handle invalid request method or missing data
    $response['message'] = 'Invalid request. Please use POST and provide both username and password.';
}

$conn->close();
echo json_encode($response);
?>