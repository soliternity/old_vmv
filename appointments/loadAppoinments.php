<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../dbconn.php';

$response = array('success' => false, 'data' => [], 'message' => '');

$sql = "SELECT
            a.appointment_id,
            a.date,
            a.starting_time,
            a.ending_time,
            CONCAT(u.fname, ' ', u.lname) AS app_user_name
        FROM appointment_schedule a
        JOIN user_schedule us ON a.appointment_id = us.appointment_id
        JOIN appusers u ON us.app_user_id = u.id
        WHERE a.status = 'confirmed'";

$result = mysqli_query($conn, $sql);

if ($result) {
    $appointments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $appointments;
} else {
    $response['message'] = 'Database query failed: ' . mysqli_error($conn);
}

mysqli_close($conn);
echo json_encode($response);
?>