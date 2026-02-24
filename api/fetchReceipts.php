<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../dbconn.php';

if (!isset($_GET['app_user_id'])) {
    die(json_encode(["error" => "app_user_id is required."]));
}

$app_user_id = $_GET['app_user_id'];

// SQL query to fetch all required data
$sql = "
SELECT
    j.created_at AS job_date_created,
    j.display_id AS job_id,
    j.customer_name,
    CONCAT(j.vehicle_brand, ' ', j.vehicle_color, ' (', j.vehicle_plate, ')') AS vehicle_details,
    GROUP_CONCAT(DISTINCT CONCAT(s.fname, ' ', s.lname) SEPARATOR ', ') AS mechanic_assigned,
    inv.invoice_number AS invoice_id,
    p.transaction_id,
    p.amount AS amount_paid,
    p.change_given AS `change`,
    p.payment_method,
    inv.total_amount,
    COALESCE(SUM(iac.amount), 0) AS additional_costs_total
FROM
    jobs j
LEFT JOIN
    job_staff js ON j.id = js.job_id
LEFT JOIN
    staff s ON js.staff_id = s.id
LEFT JOIN
    invoices inv ON j.id = inv.job_id
LEFT JOIN
    payments p ON inv.id = p.invoice_id
LEFT JOIN
    invoice_additional_costs iac ON inv.id = iac.invoice_id
WHERE
    j.au_id = ?
GROUP BY
    j.id;
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $app_user_id);
$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $job_id = $row['job_id'];

        // Fetch services and their costs for the current job
        $services_sql = "
        SELECT
            s.name AS service_name,
            js.cost AS service_cost
        FROM
            job_service js
        JOIN
            jobs j ON js.job_id = j.id
        JOIN
            services s ON js.service_id = s.id
        WHERE
            j.display_id = ?;
        ";
        $services_stmt = $conn->prepare($services_sql);
        $services_stmt->bind_param("s", $job_id);
        $services_stmt->execute();
        $services_result = $services_stmt->get_result();

        $services_rendered = [];
        $services_total_cost = 0;
        if ($services_result->num_rows > 0) {
            while ($service_row = $services_result->fetch_assoc()) {
                $services_rendered[] = [
                    "name" => $service_row['service_name'],
                    "cost" => (float)$service_row['service_cost']
                ];
                $services_total_cost += (float)$service_row['service_cost'];
            }
        }
        $services_stmt->close();
        
        // Calculate the total amount
        $total_amount_calculated = $services_total_cost + (float)$row['additional_costs_total'];

        // Add the job data to the main array
        $jobs[] = [
            "job_date_created" => $row['job_date_created'],
            "job_id" => $row['job_id'],
            "invoice_id" => $row['invoice_id'],
            "transaction_id" => $row['transaction_id'],
            "customer_name" => $row['customer_name'],
            "vehicle_details" => $row['vehicle_details'],
            "mechanic_assigned" => $row['mechanic_assigned'],
            "services_rendered" => $services_rendered,
            "additional_cost" => (float)$row['additional_costs_total'],
            "total_amount" => $total_amount_calculated,
            "amount_paid" => (float)$row['amount_paid'],
            "change" => (float)$row['change'],
            "payment_method" => $row['payment_method']
        ];
    }
} else {
    // If no jobs are found, return an empty array
    echo json_encode([]);
    exit;
}

// Return the final JSON response
echo json_encode($jobs);

// Close connections
$stmt->close();
$conn->close();

?>