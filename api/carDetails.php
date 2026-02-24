<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../dbconn.php'; // Path to your database connection file

$carId = $_GET['car_id'] ?? null;

if (empty($carId)) {
    echo json_encode(['success' => false, 'error' => 'Car ID is required']);
    exit;
}

$sql = "SELECT brand, plate, color FROM cars WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $carId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $car = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'car' => [
            'brand' => $car['brand'],
            'car_plate_no' => $car['plate'],
            'car_color' => $car['color']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Car details not found']);
}

$stmt->close();
$conn->close();
?>