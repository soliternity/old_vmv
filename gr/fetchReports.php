<?php
header('Content-Type: application/json');

// Report folder is the current directory: /b/gr/
$report_folder = 'reports/'; 

$reports = [];

if (is_dir($report_folder)) {
    // Scan the current directory for T*.pdf (Transactions) and S*.pdf (Services) files
    $files = glob($report_folder . '[TS]*.pdf');
    
    foreach ($files as $file) {
        $filename = basename($file);
        
        // Use regex to capture the file type (T or S), year, and month
        if (preg_match('/^([TS])(\d{4})(\d{2})\.pdf$/', $filename, $matches)) {
            $type_char = $matches[1];
            $year = $matches[2];
            $monthNum = (int)$matches[3];

            $dateObj = DateTime::createFromFormat('!m', $monthNum);
            $monthName = $dateObj->format('F');
            
            $title = '';
            if ($type_char === 'T') {
                $title = $monthName . ' ' . $year . ' Transaction Report';
            } elseif ($type_char === 'S') {
                $title = $monthName . ' ' . $year . ' Service/Repair Report';
            }

            $reports[] = [
                'filename' => $filename,
                'title' => $title,
                'month' => $monthName,
                'year' => $year,
                'generated_on' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
    }
}

// Sort by date (newest first)
usort($reports, function($a, $b) {
    return strtotime($b['generated_on']) - strtotime($a['generated_on']);
});

echo json_encode(['success' => true, 'reports' => $reports]);
?>