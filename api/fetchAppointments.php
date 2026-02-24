<?php

require_once '../dbconn.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => [],
    'message' => ''
];

if (isset($_GET['app_user_id']) && is_numeric($_GET['app_user_id'])) {
    // --- MODE 1: FETCH MY OWN APPOINTMENTS (For "My Bookings" screen) ---
    $user_id = (int)$_GET['app_user_id'];
    
    $sql = "SELECT 
                a.appointment_id, 
                a.date, 
                a.starting_time, 
                a.ending_time, 
                a.status, 
                a.note 
            FROM 
                appointment_schedule a
            JOIN 
                user_schedule us ON a.appointment_id = us.appointment_id
            WHERE 
                us.app_user_id = ?
            ORDER BY 
                a.date DESC, a.starting_time DESC";
                
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $appointments = $result->fetch_all(MYSQLI_ASSOC);
        
        $response['success'] = true;
        $response['data'] = $appointments;
        $response['message'] = "User appointments fetched successfully.";
    } else {
        $response['message'] = "Error fetching user appointments: " . $stmt->error;
    }
    $stmt->close();
    
} else {
    // --- MODE 2: FETCH ALL CONFIRMED APPOINTMENTS (For availability check) ---
    // The frontend uses this list to know which slots are already booked.
    $sql = "SELECT appointment_id, date, starting_time, ending_time 
            FROM appointment_schedule 
            WHERE status = 'confirmed' 
            ORDER BY date ASC, starting_time ASC";
            
    $result = $conn->query($sql);
    
    if ($result) {
        $response['success'] = true;
        $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
        $response['message'] = "All confirmed appointments fetched for availability check.";
    } else {
        $response['message'] = "Error fetching confirmed appointments: " . $conn->error;
    }
}

$conn->close();
echo json_encode($response);
?>