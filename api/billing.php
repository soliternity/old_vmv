<?php

// Set the content type to application/json
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../dbconn.php';

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if app_user_id is provided
if (!isset($data['app_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No user ID provided.']);
    exit();
}

$app_user_id = $data['app_user_id'];

// Prepare the SQL query to get ALL jobs for the specific user, and LEFT JOIN invoices
// The WHERE clause is modified to only include jobs with status 'Completed'.
$sql_jobs = "
    SELECT 
        j.id AS job_id,
        j.customer_name,
        i.id AS invoice_id,          -- Retrieve invoice ID for additional costs query
        i.invoice_number,
        i.total_amount
    FROM 
        jobs j
    LEFT JOIN
        invoices i ON j.id = i.job_id
    WHERE 
        j.au_id = ? AND j.status = 'Completed' -- <--- ADDED STATUS FILTER
    ORDER BY
        j.created_at DESC;
";

$stmt_jobs = $conn->prepare($sql_jobs);
$stmt_jobs->bind_param("i", $app_user_id);
$stmt_jobs->execute();
$result_jobs = $stmt_jobs->get_result();

$jobs = [];
$all_job_ids = [];
$invoice_to_job_map = []; // Map invoice_id to job_id

// Fetch all jobs and store them in an array
while ($row = $result_jobs->fetch_assoc()) {
    $job_id = $row['job_id'];
    $invoice_id = $row['invoice_id'];
    
    $jobs[$job_id] = [
        'job_id' => $job_id,
        'customer_name' => $row['customer_name'],
        'invoice_id' => $invoice_id,
        'invoice_number' => $row['invoice_number'],
        'total_amount' => $row['total_amount'] ? (float)$row['total_amount'] : null,
        'services' => [],
        'additional_costs' => [], // New array for additional costs
    ];
    
    $all_job_ids[] = $job_id;
    
    if ($invoice_id !== null) {
        $invoice_to_job_map[$invoice_id] = $job_id;
    }
}

$stmt_jobs->close();

if (empty($jobs)) {
    echo json_encode(['success' => true, 'jobs' => []]);
    $conn->close();
    exit();
}

// Prepare the SQL query to get services for the fetched jobs
$sql_services = "
    SELECT
        js.job_id,
        s.name AS service_name,
        js.cost AS service_cost
    FROM
        job_service js
    JOIN
        services s ON js.service_id = s.id
    WHERE
        js.job_id IN (" . implode(',', array_fill(0, count($all_job_ids), '?')) . ")
    ORDER BY
        js.job_id, s.name;
";

$stmt_services = $conn->prepare($sql_services);

// Bind job IDs to the prepared statement
$types = str_repeat('i', count($all_job_ids));
$stmt_services->bind_param($types, ...$all_job_ids);
$stmt_services->execute();
$result_services = $stmt_services->get_result();

// Loop through the services and add them to the corresponding job in the $jobs array
while ($row = $result_services->fetch_assoc()) {
    $job_id = $row['job_id'];
    if (isset($jobs[$job_id])) {
        $jobs[$job_id]['services'][] = [
            'service_name' => $row['service_name'],
            'service_cost' => (float)$row['service_cost'],
        ];
    }
}

$stmt_services->close();


// --- Fetching Additional Costs ---

if (!empty($invoice_to_job_map)) {
    $all_invoice_ids = array_keys($invoice_to_job_map);
    
    // Prepare the SQL query to get additional costs for invoices that exist
    $sql_additional_costs = "
        SELECT
            iac.invoice_id,
            iac.description,
            iac.amount
        FROM
            invoice_additional_costs iac
        WHERE
            iac.invoice_id IN (" . implode(',', array_fill(0, count($all_invoice_ids), '?')) . ")
        ORDER BY
            iac.id;
    ";
    
    $stmt_costs = $conn->prepare($sql_additional_costs);
    
    // Bind invoice IDs to the prepared statement
    $types_costs = str_repeat('i', count($all_invoice_ids));
    $stmt_costs->bind_param($types_costs, ...$all_invoice_ids);
    $stmt_costs->execute();
    $result_costs = $stmt_costs->get_result();

    // Loop through the additional costs and add them to the corresponding job
    while ($row = $result_costs->fetch_assoc()) {
        $invoice_id = $row['invoice_id'];
        $job_id = $invoice_to_job_map[$invoice_id]; // Get the job_id from the map
        
        if (isset($jobs[$job_id])) {
            $jobs[$job_id]['additional_costs'][] = [
                'description' => $row['description'],
                'amount' => (float)$row['amount'],
            ];
        }
    }
    
    $stmt_costs->close();
}


$conn->close();

// Re-index the jobs array to be a simple list of objects
$final_jobs = array_values($jobs);

// Encode the final array to JSON and output
echo json_encode(['success' => true, 'jobs' => $final_jobs]);

?>