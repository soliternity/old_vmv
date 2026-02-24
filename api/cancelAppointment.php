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

if (empty($appointment_id) || empty($app_user_id)) {
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

    // Update the appointment status to 'cancelled'
    $stmt = $conn->prepare("UPDATE appointment_schedule SET status = 'cancelled' WHERE appointment_id = ? AND status = 'confirmed'");
    $stmt->bind_param("i", $appointment_id);
    if (!$stmt->execute()) {
        throw new Exception("Error cancelling appointment: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Appointment could not be cancelled. It may have already been cancelled or expired.");
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>