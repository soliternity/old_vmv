<?php
header('Content-Type: application/json');
require_once '../dbconn.php'; // Use the specified connection file

// Check connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['customerName'], $data['dateInput'], $data['timeSlot'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required input fields.']);
    $conn->close();
    exit();
}

$customerName = trim($data['customerName']);
$appointmentDate = $data['dateInput']; // YYYY-MM-DD
$timeSlot = $data['timeSlot']; // e.g., "8:00 - 9:00"

// 1. Parse time slot
$times = explode(' - ', $timeSlot);
if (count($times) !== 2) {
    echo json_encode(['success' => false, 'message' => 'Invalid time slot format.']);
    $conn->close();
    exit();
}
// Convert to HH:MM:SS format
$startingTime = date('H:i:s', strtotime($times[0]));
$endingTime = date('H:i:s', strtotime($times[1]));

// Start transaction
$conn->begin_transaction();
$appUserId = null;

try {
    // 2. Find App User ID
    // Note: We search for the customer by matching the concatenated name from the database.
    $stmt_user = $conn->prepare("SELECT id FROM appusers WHERE CONCAT(fname, ' ', lname) = ? OR CONCAT(fname, ' ', mname, ' ', lname) = ?");
    $stmt_user->bind_param("ss", $customerName, $customerName);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 0) {
        throw new Exception("Customer not found in the database. Please select an existing customer.");
    }

    $appUserId = $result_user->fetch_assoc()['id'];
    $stmt_user->close();

    // NEW LOGIC: Check for existing confirmed appointment for this user on *any* date
    $stmt_duplicate_check = $conn->prepare("
        SELECT 1
        FROM user_schedule us
        JOIN appointment_schedule a ON us.appointment_id = a.appointment_id
        WHERE us.app_user_id = ? AND a.status = 'confirmed'
    ");
    $stmt_duplicate_check->bind_param("i", $appUserId);
    $stmt_duplicate_check->execute();
    $result_duplicate = $stmt_duplicate_check->get_result();

    if ($result_duplicate->num_rows > 0) {
        $stmt_duplicate_check->close();
        throw new Exception("This customer already has one confirmed appointment. Only one confirmed appointment is allowed per customer.");
    }
    $stmt_duplicate_check->close();
    // END NEW LOGIC
    
    // 3. Check if the time slot is already booked by another user (standard time slot check)
    $stmt_booked = $conn->prepare("SELECT 1 FROM appointment_schedule WHERE date = ? AND starting_time = ? AND ending_time = ? AND status = 'confirmed'");
    $stmt_booked->bind_param("sss", $appointmentDate, $startingTime, $endingTime);
    $stmt_booked->execute();
    if ($stmt_booked->get_result()->num_rows > 0) {
        throw new Exception("This time slot is already booked. Please choose another one.");
    }
    $stmt_booked->close();

    // 4. Check if the time slot is marked as generally unavailable by date_rules (standard rule check)
    $stmt_rule_slot = $conn->prepare("SELECT 1 FROM date_rules WHERE date = ? AND starting_time = ? AND ending_time = ? AND status = 'available'");
    $stmt_rule_slot->bind_param("sss", $appointmentDate, $startingTime, $endingTime);
    $stmt_rule_slot->execute();
    if ($stmt_rule_slot->get_result()->num_rows > 0) {
        throw new Exception("This specific time slot is marked as unavailable by the date rules.");
    }
    $stmt_rule_slot->close();

    // 5. Insert into appointment_schedule
    $stmt_appt = $conn->prepare("INSERT INTO appointment_schedule (date, starting_time, ending_time, status) VALUES (?, ?, ?, 'confirmed')");
    $stmt_appt->bind_param("sss", $appointmentDate, $startingTime, $endingTime);
    $stmt_appt->execute();
    $appointmentId = $conn->insert_id;
    $stmt_appt->close();

    if (!$appointmentId) {
         throw new Exception("Failed to insert into appointment schedule.");
    }

    // 6. Insert into user_schedule
    $stmt_user_sch = $conn->prepare("INSERT INTO user_schedule (appointment_id, app_user_id) VALUES (?, ?)");
    $stmt_user_sch->bind_param("ii", $appointmentId, $appUserId);
    $stmt_user_sch->execute();
    $stmt_user_sch->close();

    // Commit transaction
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Appointment successfully booked!']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    // Output the error message for the client-side to display
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);

} finally {
    // Close connection
    $conn->close();
}
?>