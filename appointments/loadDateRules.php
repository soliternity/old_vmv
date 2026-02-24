<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include_once '../dbconn.php';

$response = array('success' => false, 'data' => [], 'message' => '');

$sql = "SELECT date, starting_time, ending_time, status FROM date_rules";

$result = mysqli_query($conn, $sql);

if ($result) {
    $rules = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rules[] = $row;
    }
    $response['success'] = true;
    $response['data'] = $rules;
} else {
    $response['message'] = 'Database query failed: ' . mysqli_error($conn);
}

mysqli_close($conn);
echo json_encode($response);
?>