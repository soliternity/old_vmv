<?php

// Set the content type to JSON
header('Content-Type: application/json');

require_once '../dbconn.php';

// SQL query to fetch all staff with the 'mechanic' role without their email
$sql = "SELECT id, fname, lname FROM staff WHERE role = 'mechanic' ORDER BY fname ASC, lname ASC";
$result = $conn->query($sql);

$mechanics = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $mechanics[] = [
            'id' => $row['id'],
            'name' => trim($row['fname'] . ' ' . $row['lname'])
        ];
    }
}

// Close the database connection
$conn->close();

// Output the JSON
echo json_encode($mechanics, JSON_PRETTY_PRINT);
?>