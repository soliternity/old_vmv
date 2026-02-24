<?php
require_once '../dbconn.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$unavailable_dates = [];
$unavailable_time_slots = [];

// Fetch dates that are marked as 'unavailable' in date_rules
$result = $conn->query("SELECT DISTINCT date FROM date_rules WHERE status = 'unavailable'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $unavailable_dates[] = $row['date'];
    }
}

// Fetch time slots that are marked as 'unavailable' in date_rules
$result = $conn->query("SELECT date, starting_time, ending_time FROM date_rules WHERE status = 'unavailable' AND starting_time IS NOT NULL AND ending_time IS NOT NULL");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $unavailable_time_slots[] = $row;
    }
}

// Fetch confirmed appointments to mark their slots as unavailable
$current_date = date('Y-m-d');
$result = $conn->query("SELECT date, starting_time, ending_time FROM appointment_schedule WHERE status = 'confirmed' AND date >= '$current_date'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $unavailable_time_slots[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'unavailable_dates' => array_values(array_unique($unavailable_dates)),
    'unavailable_time_slots' => $unavailable_time_slots
]);

$conn->close();
?>