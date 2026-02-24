<?php
// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Database connection details (replace with your actual connection logic)
// Assuming a file named 'db_connect.php' with a global $conn variable
// require_once 'db_connect.php'; 
require_once '../dbconn.php';
// --- END DB CONNECTION MOCKUP ---

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['conversation_id']) && is_numeric($_GET['conversation_id'])) {
        $conversation_id = $conn->real_escape_string($_GET['conversation_id']);

        $query = "
            SELECT 
                m.message_id, 
                m.sender_type, 
                m.content, 
                m.sent_at 
            FROM 
                message m
            WHERE 
                m.conversation_id = '$conversation_id'
            ORDER BY 
                m.sent_at ASC
        ";

        $result = $conn->query($query);

        if ($result) {
            $messages = array();
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            http_response_code(200);
            echo json_encode($messages);
        } else {
            http_response_code(500);
            echo json_encode(array("message" => "Error executing query: " . $conn->error));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Missing or invalid conversation_id."));
    }

    $conn->close();

} else {
    http_response_code(405);
    echo json_encode(array("message" => "Method not allowed."));
}
?>