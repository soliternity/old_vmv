<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database connection details (replace with your actual connection logic)
// Assuming a file named 'db_connect.php' with a global $conn variable
// require_once 'db_connect.php'; 

require_once '../dbconn.php';
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
    $search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

    // Base query to select conversation data
    $query = "
        SELECT 
            c.conversation_id,
            c.status,
            c.updated_at,
            au.fname AS user_fname,
            au.lname AS user_lname,
            s.fname AS staff_fname,
            s.lname AS staff_lname,
            (SELECT content FROM message m WHERE m.conversation_id = c.conversation_id ORDER BY m.sent_at DESC LIMIT 1) AS last_message_content,
            (SELECT sender_type FROM message m WHERE m.conversation_id = c.conversation_id ORDER BY m.sent_at DESC LIMIT 1) AS last_message_sender_type
        FROM 
            conversation c
        JOIN 
            appusers au ON c.app_user_id = au.id
        JOIN 
            staff s ON c.staff_id = s.id
        WHERE 1=1 
    ";

    // Apply status filter
    if (in_array($filter_status, ['not_done', 'done'])) {
        $query .= " AND c.status = '$filter_status'";
    }

    // Apply search filter (Search by user name or staff name)
    if (!empty($search_term)) {
        $query .= " 
            AND (
                au.fname LIKE '%$search_term%' OR 
                au.lname LIKE '%$search_term%' OR 
                s.fname LIKE '%$search_term%' OR 
                s.lname LIKE '%$search_term%'
            )
        ";
    }

    // Order by the time of the last update
$query .= " ORDER BY FIELD(c.status, 'not_done', 'done') ASC, c.updated_at DESC";

    $result = $conn->query($query);

    if ($result) {
        $conversations = array();
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
        http_response_code(200);
        echo json_encode($conversations);
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Error executing query: " . $conn->error));
    }

    $conn->close();

} else {
    http_response_code(405);
    echo json_encode(array("message" => "Method not allowed."));
}
?>