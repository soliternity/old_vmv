<?php
header('Content-Type: application/json');

require_once '../dbconn.php';
// Report folder is the current directory: /b/gr/
$report_folder = 'reports/'; 

// --- 2. Get Input Data ---
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['report_type']) || !isset($input['start_date']) || !isset($input['end_date']) || !isset($input['file_name'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters.']);
    exit;
}

$start_date = $input['start_date'];
$end_date = $input['end_date'];
$file_name = $input['file_name'];
$full_path = $report_folder . $file_name;

// --- 3. Check if file already exists ---
if (file_exists($full_path)) {
    echo json_encode(['success' => true, 'should_create' => false, 'message' => 'File already exists.']);
    exit;
}


if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$data = [];
$summary = [];

// A. Fetch Detailed Service/Repair Data 
// Filtered by job status 'Completed' and job creation date (jobs.created_at).
$sql_data = "
    SELECT
        j.display_id AS job_order_number,
        s.name AS service_name,
        -- Aggregate all mechanics assigned to the job
        GROUP_CONCAT(DISTINCT CONCAT(st.fname, ' ', st.lname) SEPARATOR ', ') AS assigned_mechanic,
        js.started_at,
        js.completed_at,
        j.status,
        s.id AS service_id
    FROM jobs j
    JOIN job_service js ON j.id = js.job_id
    JOIN services s ON js.service_id = s.id
    LEFT JOIN job_staff jst ON j.id = jst.job_id
    LEFT JOIN staff st ON jst.staff_id = st.id
    WHERE j.status = 'Paid' 
      -- Filter by the job order creation date
      AND DATE(j.created_at) >= ? AND DATE(j.created_at) <= ?
    GROUP BY 
        j.id, s.id, js.started_at, js.completed_at, j.status, j.display_id, s.name, s.id 
    ORDER BY s.id ASC, j.id ASC 
";

$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param("ss", $start_date, $end_date);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

while ($row = $result_data->fetch_assoc()) {
    $data[] = $row;
}
$stmt_data->close();


// B. Fetch Service Usage Summary (Count of service type used)
// Filtered by job status 'Completed' and job creation date (jobs.created_at).
$sql_summary = "
    SELECT 
        s.name AS service_name, 
        COUNT(js.service_id) AS count
    FROM jobs j
    JOIN job_service js ON j.id = js.job_id
    JOIN services s ON js.service_id = s.id
    WHERE j.status = 'Paid'
      -- Filter by the job order creation date
      AND DATE(j.created_at) >= ? AND DATE(j.created_at) <= ?
    GROUP BY s.name
    ORDER BY count DESC
";

$stmt_summary = $conn->prepare($sql_summary);
$stmt_summary->bind_param("ss", $start_date, $end_date);
$stmt_summary->execute();
$result_summary = $stmt_summary->get_result();

while ($row = $result_summary->fetch_assoc()) {
    $summary[] = $row;
}
$stmt_summary->close();

$conn->close();

// Format report period for front-end display
$report_period = date('n/j/Y', strtotime($start_date)) . ' - ' . date('n/j/Y', strtotime($end_date));

echo json_encode([
    'success' => true, 
    'should_create' => true, 
    'data' => $data,
    'summary' => $summary,
    'reportPeriod' => $report_period
]);
?>