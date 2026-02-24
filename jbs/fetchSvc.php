<?php

// Set the content type to JSON
header('Content-Type: application/json');

require_once '../dbconn.php';

// SQL query to fetch all services without hours
$sql = "SELECT id, name, min_cost, max_cost FROM services ORDER BY name ASC";
$result = $conn->query($sql);

$services = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Close the database connection
$conn->close();

// Output the JSON
echo json_encode($services, JSON_PRETTY_PRINT);
?>