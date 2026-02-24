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

$appointment_id = $data['appointment_id'] ?? null;
$app_user_id = $data['app_user_id'] ?? null;
$new_date = $data['new_date'] ?? null;
$new_starting_time = $data['new_starting_time'] ?? null;
$new_ending_time = $data['new_ending_time'] ?? null;

if (empty($appointment_id) || empty($app_user_id) || empty($new_date) || empty($new_starting_time) || empty($new_ending_time)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit();
}

$conn->begin_transaction();

try {
    // Verify the appointment belongs to the user
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_schedule WHERE appointment_id = ? AND app_user_id = ?");
    $stmt->bind_param("ii", $appointment_id, $app_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    if ($row[0] == 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Appointment not found or does not belong to this user.']);
        exit();
    }
    
    // Check if the new date is unavailable based on date_rules
    $stmt = $conn->prepare("SELECT status FROM date_rules WHERE date = ? AND status = 'unavailable'");
    $stmt->bind_param("s", $new_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This new date is unavailable.']);
        exit();
    }

    // Check if the specific new time slot is already booked (excluding the current appointment being rescheduled)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM appointment_schedule WHERE date = ? AND starting_time = ? AND ending_time = ? AND status = 'confirmed' AND appointment_id != ?");
    $stmt->bind_param("sssi", $new_date, $new_starting_time, $new_ending_time, $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    if ($row[0] > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This new time slot is already booked.']);
        exit();
    }
    
    // Check if the specific time slot is marked as unavailable in date_rules
    $stmt = $conn->prepare("SELECT status FROM date_rules WHERE date = ? AND starting_time = ? AND ending_time = ? AND status = 'unavailable'");
    $stmt->bind_param("sss", $new_date, $new_starting_time, $new_ending_time);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'This new time slot is unavailable.']);
        exit();
    }

    // Update the appointment_schedule with the new date and time
    $stmt = $conn->prepare("UPDATE appointment_schedule SET date = ?, starting_time = ?, ending_time = ?, status = 'confirmed' WHERE appointment_id = ?");
    $stmt->bind_param("sssi", $new_date, $new_starting_time, $new_ending_time, $appointment_id);
    if (!$stmt->execute()) {
        throw new Exception("Error rescheduling appointment: " . $stmt->error);
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Appointment rescheduled successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>