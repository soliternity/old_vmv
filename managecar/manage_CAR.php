<?php
// manage_CAR.php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once '../dbconn.php';

if (!isset($conn)) {
    echo json_encode(["error" => "Database connection failed."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ===========================================================
// GET — Retrieve cars
// ===========================================================
if ($method === 'GET') {
    $appuser_id = $_GET['appuser_id'] ?? $_POST['appuser_id'] ?? null;

    if (!$appuser_id) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, brand, color, plate, appuser_id FROM cars WHERE appuser_id = ? ORDER BY id");
    $stmt->bind_param("i", $appuser_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $cars = [];
    while ($row = $result->fetch_assoc()) {
        $cars[] = $row;
    }

    echo json_encode($cars, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===========================================================
// POST — Save & Delete cars (bulk from Flutter)
// ===========================================================
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        echo json_encode(["success" => false, "message" => "Invalid JSON"]);
        exit;
    }

    $carsToSave = $input['cars_to_save'] ?? [];
    $carsToDelete = $input['cars_to_delete'] ?? [];

    $savedCars = 0;
    $deletedCars = 0;

    // ---- Handle deletions ----
    if (!empty($carsToDelete)) {
        $deleteStmt = $conn->prepare("DELETE FROM cars WHERE id = ? AND appuser_id = ?");

        foreach ($carsToDelete as $deleteId) {
            $appuser_id = $carsToSave[0]['appuser_id'] ?? null;
            $deleteStmt->bind_param("ii", $deleteId, $appuser_id);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows > 0) {
                $deletedCars++;
            }
        }
    }

    // ---- Handle saves/updates ----
    $insertStmt = $conn->prepare("INSERT INTO cars (brand, color, plate, appuser_id) VALUES (?, ?, ?, ?)");
    $updateStmt = $conn->prepare("UPDATE cars SET brand = ?, color = ?, plate = ? WHERE id = ? AND appuser_id = ?");
    $countStmt  = $conn->prepare("SELECT COUNT(*) FROM cars WHERE appuser_id = ?");

    foreach ($carsToSave as $car) {
        $appuser_id = $car['appuser_id'] ?? null;
        $brand = trim($car['brand'] ?? '');
        $color = trim($car['color'] ?? '');
        $plate = trim($car['plate'] ?? '');
        $car_id = $car['car_id'] ?? null;

        if (!$appuser_id || empty($brand) || empty($color) || empty($plate)) {
            continue;
        }

        // Limit 3 cars per user
        $countStmt->bind_param("i", $appuser_id);
        $countStmt->execute();
        $countStmt->bind_result($carCount);
        $countStmt->fetch();
        $countStmt->free_result();

        if (!$car_id && $carCount >= 3) {
            continue;
        }

        if ($car_id) {
            $updateStmt->bind_param("sssii", $brand, $color, $plate, $car_id, $appuser_id);
            $updateStmt->execute();

            if ($updateStmt->affected_rows > 0) {
                $savedCars++;
            }
        } else {
            $insertStmt->bind_param("sssi", $brand, $color, $plate, $appuser_id);
            $insertStmt->execute();

            if ($insertStmt->affected_rows > 0) {
                $savedCars++;
            }
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Operation completed",
        "cars_saved" => $savedCars,
        "cars_deleted" => $deletedCars
    ]);
    exit;
}

// ===========================================================
// DELETE — Remove single car
// ===========================================================
elseif ($method === 'DELETE') {
    parse_str(file_get_contents("php://input"), $input);

    $car_id = $input['car_id'] ?? null;
    $appuser_id = $input['appuser_id'] ?? null;

    if (!$car_id || !$appuser_id) {
        echo json_encode(['success' => false, 'message' => 'car_id and appuser_id are required']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM cars WHERE id = ? AND appuser_id = ?");
    $stmt->bind_param("ii", $car_id, $appuser_id);
    $stmt->execute();

    echo json_encode([
        'success' => $stmt->affected_rows > 0,
        'message' => $stmt->affected_rows > 0 ? 'Car deleted successfully' : 'Car not found or unauthorized'
    ]);
    exit;
}

// ===========================================================
// INVALID METHOD
// ===========================================================
else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
