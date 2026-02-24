<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../dbconn.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->app_user_id)) {
    echo json_encode(['success' => false, 'error' => 'User ID is required.']);
    exit;
}

$userId = $data->app_user_id;

$conn->begin_transaction();

try {
    // Update user profile
    $sqlUser = "UPDATE appusers SET fname = ?, mname = ?, lname = ? WHERE id = ?";
    $stmtUser = $conn->prepare($sqlUser);
    $stmtUser->bind_param("sssi",
        $data->first_name,
        $data->middle_name,
        $data->last_name,
        $userId
    );
    $stmtUser->execute();

    // Check if car details are provided
    if (isset($data->brand) && isset($data->car_color) && isset($data->car_plate_no)) {
        $plateNo = trim($data->car_plate_no);
        $carId = null;

        if (!empty($plateNo)) {
            // Check if a car already exists for this user
            $sqlCheckCar = "SELECT id FROM cars WHERE appuser_id = ?";
            $stmtCheckCar = $conn->prepare($sqlCheckCar);
            $stmtCheckCar->bind_param("i", $userId);
            $stmtCheckCar->execute();
            $resultCheckCar = $stmtCheckCar->get_result();
            $existingCar = $resultCheckCar->fetch_assoc();
            $stmtCheckCar->close();

            if ($existingCar) {
                // Update existing car entry
                $sqlCar = "UPDATE cars SET brand = ?, color = ?, plate = ? WHERE id = ?";
                $stmtCar = $conn->prepare($sqlCar);
                $stmtCar->bind_param("sssi",
                    $data->brand,
                    $data->car_color,
                    $plateNo,
                    $existingCar['id']
                );
                $stmtCar->execute();
                $carId = $existingCar['id'];
                $stmtCar->close();
            } else {
                // Insert new car entry
                $sqlCar = "INSERT INTO cars (brand, color, plate, appuser_id) VALUES (?, ?, ?, ?)";
                $stmtCar = $conn->prepare($sqlCar);
                $stmtCar->bind_param("sssi",
                    $data->brand,
                    $data->car_color,
                    $plateNo,
                    $userId
                );
                $stmtCar->execute();
                $carId = $conn->insert_id;
                $stmtCar->close();
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.', 'car_id' => $carId]);
} catch (mysqli_sql_exception $exception) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
}

$stmtUser->close();
$conn->close();
?>