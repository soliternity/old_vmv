<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT');
header('Access-Control-Allow-Headers: origin, content-type, accept, x-requested-with');

if (isset($_GET['file_name'])) {
    $fileName = basename($_GET['file_name']);
    $filePath = 'reports/' . $fileName;

    if (file_exists($filePath) && is_readable($filePath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        
        ob_clean();
        flush();
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo "File not found.";
    }
} else {
    http_response_code(400);
    echo "Missing file_name parameter.";
}