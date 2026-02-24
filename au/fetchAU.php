<?php
// Start a session and check user role
session_start();

// Ensure the user is logged in and has an 'admin' or 'manager' role
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    // Set the Content-Type header to application/json
    header('Content-Type: application/json');

    // Return a JSON error message instead of a redirect
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

// If authorized, continue to set content type and fetch data
header('Content-Type: application/json');
require_once '../dbconn.php';

// Fetch all users
$users_sql = "SELECT au.id, au.lname, au.fname, au.mname, au.email, au.password, au.created_at
              FROM appusers au";
$users_result = $conn->query($users_sql);

$users = [];
if ($users_result->num_rows > 0) {
    while ($row = $users_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch all cars
$cars_sql = "SELECT * FROM cars";
$cars_result = $conn->query($cars_sql);

$cars = [];
if ($cars_result->num_rows > 0) {
    while ($row = $cars_result->fetch_assoc()) {
        $cars[] = $row;
    }
}

// Combine data into a single array
$data = [
    'users' => $users,
    'cars' => $cars
];

// Encode the combined data as JSON and output it
echo json_encode($data);

// Close the database connection
$conn->close();
?>