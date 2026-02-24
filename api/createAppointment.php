<?php
require_once '../dbconn.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

$app_user_id = $data['app_user_id'] ?? null;
$date = $data['date'] ?? null;
$starting_time = $data['starting_time'] ?? null;
$ending_time = $data['ending_time'] ?? null;
$note = $data['note'] ?? null;

if (empty($app_user_id) || empty($date) || empty($starting_time) || empty($ending_time)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit();
}

$conn->begin_transaction();

try {
    // Check if the date is unavailable based on date_rules
    $stmt = $conn->prepare("SELECT status FROM date_rules WHERE date = ? AND status = 'unavailable'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This date is unavailable.']);
        exit();
    }

    // Check if the specific time slot is already booked
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointment_schedule WHERE date = ? AND starting_time = ? AND ending_time = ? AND status != 'cancelled'");
    $stmt->bind_param("sss", $date, $starting_time, $ending_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    if ($row[0] > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This time slot is already booked.']);
        exit();
    }
    
    // Check if the specific time slot is marked as unavailable in date_rules
    $stmt = $conn->prepare("SELECT status FROM date_rules WHERE date = ? AND starting_time = ? AND ending_time = ? AND status = 'unavailable'");
    $stmt->bind_param("sss", $date, $starting_time, $ending_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This time slot is unavailable.']);
        exit();
    }

    // Insert into appointment_schedule
    $stmt = $conn->prepare("INSERT INTO appointment_schedule (date, starting_time, ending_time, note) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $date, $starting_time, $ending_time, $note);
    if (!$stmt->execute()) {
        throw new Exception("Error inserting into appointment_schedule: " . $stmt->error);
    }
    $appointment_id = $stmt->insert_id;
    
    // Insert into user_schedule
    $stmt = $conn->prepare("INSERT INTO user_schedule (appointment_id, app_user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $appointment_id, $app_user_id);
    if (!$stmt->execute()) {
        throw new Exception("Error inserting into user_schedule: " . $stmt->error);
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Appointment created successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>