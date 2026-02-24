<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: origin, content-type, accept, x-requested-with');

require_once '../dbconn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $staffName = $data['staffName'];
    $startDate = $data['startDate'];
    $endDate = $data['endDate'];
    $pdfData = $data['pdfData'];
    $baseFileName = $data['fileName'];

    $reportsFolder = 'reports/';
    if (!is_dir($reportsFolder)) {
        mkdir($reportsFolder, 0777, true);
    }

    $fileCounter = 1;
    $fileName = $baseFileName;
    while (file_exists($reportsFolder . $fileName)) {
        $fileInfo = pathinfo($baseFileName);
        $fileName = $fileInfo['filename'] . '_' . $fileCounter . '.' . $fileInfo['extension'];
        $fileCounter++;
    }

    $filePath = $reportsFolder . $fileName;
    $success = file_put_contents($filePath, base64_decode($pdfData));

    if ($success !== false) {
        $sql = "INSERT INTO pdf_reports (staff_name, start_date, end_date, file_name) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $staffName, $startDate, $endDate, $fileName);

        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Report saved successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to save PDF file"]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}

$conn->close();
