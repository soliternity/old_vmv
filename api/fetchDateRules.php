<?php

require_once '../dbconn.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

// Fetch all date rules. The frontend will interpret these as blocking rules
// based on the date and time slots provided.
$sql = "SELECT rule_id, date, starting_time, ending_time, status 
        FROM date_rules 
        ORDER BY date ASC";
        
$result = $conn->query($sql);

if ($result) {
    $rules = $result->fetch_all(MYSQLI_ASSOC);
    
    // Convert NULL times to empty string for consistent JSON handling in Dart
    foreach ($rules as &$rule) {
        $rule['starting_time'] = $rule['starting_time'] ?? '';
        $rule['ending_time'] = $rule['ending_time'] ?? '';
    }
    
    $response['success'] = true;
    $response['data'] = $rules;
    $response['message'] = "Date rules (blocking/unavailability) fetched successfully.";
} else {
    $response['message'] = "Error fetching date rules: " . $conn->error;
}

$conn->close();
echo json_encode($response);
?>