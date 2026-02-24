<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../dbconn.php';

$response = array('success' => false, 'message' => '');

$input = file_get_contents('php://input');
$payload = json_decode($input, true);

if (!isset($payload['dates']) || !is_array($payload['dates'])) {
    $response['message'] = 'Invalid input.';
    echo json_encode($response);
    exit;
}

mysqli_begin_transaction($conn);

try {
    foreach ($payload['dates'] as $dateRule) {
        $date = mysqli_real_escape_string($conn, $dateRule['date']);
        $day_status = mysqli_real_escape_string($conn, $dateRule['status']);
        
        // Always start by deleting any existing rules for this date
        $deleteSql = "DELETE FROM date_rules WHERE date = '$date'";
        if (!mysqli_query($conn, $deleteSql)) {
            throw new Exception('Failed to delete existing rules.');
        }

        if ($day_status === 'unavailable') {
            // Case 1: The entire day is unavailable. This status remains "unavailable"
            $insertSql = "INSERT INTO date_rules (date, starting_time, ending_time, status) 
                          VALUES ('$date', NULL, NULL, 'unavailable')";
            if (!mysqli_query($conn, $insertSql)) {
                throw new Exception('Failed to insert unavailable day rule.');
            }
        } else {
            // Case 2: Day is available, but some time slots are unavailable.
            // As per your new logic, these are saved with status = 'available'
            $unavailableSlots = isset($dateRule['unavailable_slots']) ? $dateRule['unavailable_slots'] : [];
            
            if (!empty($unavailableSlots)) {
                foreach ($unavailableSlots as $slot) {
                    list($start_time, $end_time) = explode(' - ', $slot);
                    $start_time = mysqli_real_escape_string($conn, trim($start_time) . ':00');
                    $end_time = mysqli_real_escape_string($conn, trim($end_time) . ':00');

                    $insertSql = "INSERT INTO date_rules (date, starting_time, ending_time, status) 
                                  VALUES ('$date', '$start_time', '$end_time', 'available')";
                    if (!mysqli_query($conn, $insertSql)) {
                        throw new Exception('Failed to insert unavailable time slot rule.');
                    }
                }
            }
        }
    }

    mysqli_commit($conn);
    $response['success'] = true;
    $response['message'] = 'Availability rules saved successfully.';
} catch (Exception $e) {
    mysqli_rollback($conn);
    $response['message'] = $e->getMessage() . ' Transaction rolled back.';
}

mysqli_close($conn);
echo json_encode($response);
?>