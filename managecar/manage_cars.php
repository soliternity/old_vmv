<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Include your database connection
// 🛑 IMPORTANT: Para makita ang errors, idagdag ito sa dulo ng dbconn.php o dito:
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../dbconn.php';

// Check DB connection
if (!isset($conn)) {
    echo json_encode(["error" => "Database connection failed."]);
    exit;
}

// Handle HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Handle CORS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($method) {
    
    // READ cars (all, by id, or by user)
    case 'GET':
        if (isset($_GET['id'])) {
            // Get specific car
            $id = intval($_GET['id']);
            $sql = "SELECT * FROM cars WHERE id = $id";
        } elseif (isset($_GET['appuser_id'])) {
            // Get all cars owned by a user
            $appuser_id = intval($_GET['appuser_id']);
            $sql = "SELECT * FROM cars WHERE appuser_id = $appuser_id";
        } else {
            // Get all cars
            $sql = "SELECT * FROM cars";
        }

        $result = $conn->query($sql);
        $cars = [];

        while ($row = $result->fetch_assoc()) {
            $cars[] = $row;
        }

        echo json_encode($cars);
        break;

    // ✅ FIXED: I-handle ang INSERT (kung walang car_id) at UPDATE (kung may car_id)
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);

        // Ang Flutter code ay nagpapadala ng array ng cars, o isang car object.
        // Hahawakan natin ang array case para sa multiple cars save.
        $cars_to_process = (isset($data[0]) && is_array($data[0])) ? $data : [$data];
        $responses = [];

        foreach ($cars_to_process as $car) {
            
            // Check required fields (tandaan: car_id ay optional para sa INSERT)
            if (!isset($car['brand'], $car['color'], $car['plate'], $car['appuser_id'])) {
                $responses[] = ["error" => "Missing required fields for one car."];
                continue; // Skip this car if fields are missing
            }

            // Kunin ang car_id (galing sa Flutter)
            $car_id = $car['car_id'] ?? null; 

            // Escape strings for security (iwas SQL Injection)
            $brand = $conn->real_escape_string($car['brand']);
            $color = $conn->real_escape_string($car['color']);
            $plate = $conn->real_escape_string($car['plate']);
            $appuser_id = intval($car['appuser_id']);

            if ($car_id) {
                // OPERATION: UPDATE (Existing Car)
                $id = intval($car_id);
                $sql = "UPDATE cars 
                        SET brand='$brand', color='$color', plate='$plate', appuser_id=$appuser_id 
                        WHERE id=$id";
                
                if ($conn->query($sql)) {
                    $responses[] = ["success" => true, "action" => "updated", "id" => $id];
                } else {
                    $responses[] = ["success" => false, "error" => $conn->error, "car_id" => $id, "action" => "update_failed"];
                }
            } else {
                // OPERATION: INSERT (New Car)
                $sql = "INSERT INTO cars (brand, color, plate, appuser_id)
                        VALUES ('$brand', '$color', '$plate', $appuser_id)";

                if ($conn->query($sql)) {
                    $responses[] = ["success" => true, "action" => "inserted", "id" => $conn->insert_id];
                } else {
                    // Ito ang lalabas kapag duplicate ang plate number
                    $responses[] = ["success" => false, "error" => $conn->error, "action" => "insert_failed"];
                }
            }
        }
        
        // Ibalik ang sagot, kung isang object lang ang pinadala, isang object lang din ang ibalik
        if (count($responses) === 1 && $data === $cars_to_process[0]) {
             echo json_encode($responses[0]);
        } else {
             echo json_encode($responses);
        }
        break;

    // 🛑 Hindi na kailangan ang PUT method dahil inayos na natin ang POST.
    // Tanggalin mo ang 'case 'PUT'' o hayaan na lang itong walang laman kung kailangan pa.
    case 'PUT':
        // Palitan ng error o i-redirect sa POST logic kung gusto mo
        echo json_encode(["error" => "Use POST method for car updates with 'car_id' in body."]);
        break;

    // DELETE a car
    case 'DELETE':
        if (!isset($_GET['id'])) {
            echo json_encode(["error" => "Missing car ID."]);
            exit;
        }

        $id = intval($_GET['id']);
        $sql = "DELETE FROM cars WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["error" => $conn->error]);
        }
        break;

    default:
        echo json_encode(["error" => "Unsupported request method."]);
        break;
}

$conn->close();
?>