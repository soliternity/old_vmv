<?php

// Set the content type to JSON
header('Content-Type: application/json');

require_once '../dbconn.php';

// SQL query to fetch customer details and their cars
$sql = "SELECT 
            au.id, 
            CONCAT(au.fname, ' ', au.lname) AS name,
            c.brand,
            c.color,
            c.plate
        FROM 
            appusers AS au
        LEFT JOIN
            cars AS c ON au.id = c.appuser_id
        ORDER BY 
            au.fname ASC, au.lname ASC";
$result = $conn->query($sql);

$customers = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Close the database connection
$conn->close();

// Output the JSON
echo json_encode($customers, JSON_PRETTY_PRINT);
?>