<?php
header('Content-Type: application/json');

// Report folder is the current directory: /b/gr/
$report_folder = 'reports/'; 

// Ensure directory exists (though it should since the script is in it)
if (!is_dir($report_folder)) {
    mkdir($report_folder, 0777, true);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['fileName']) || !isset($input['pdfData'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received.']);
    exit;
}

$file_name = $input['fileName'];
$base64_pdf = $input['pdfData'];
$pdf_binary = base64_decode($base64_pdf);
$full_path = $report_folder . $file_name;

if ($pdf_binary === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to decode Base64 PDF data.']);
    exit;
}

// Write the binary PDF data to the file in the current directory
if (file_put_contents($full_path, $pdf_binary) !== false) {
    echo json_encode(['success' => true, 'fileName' => $file_name]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write file to disk. Check folder permissions.']);
}
?>