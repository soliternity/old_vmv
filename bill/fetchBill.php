<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$response = [
    'success' => false,
    'message' => 'Invalid request or job not found.'
];

if (isset($_GET['job_id'])) {
    $jobDisplayId = $_GET['job_id'];

    // Fetch job details to get the actual job_id
    $sqlJob = "
    SELECT
        j.id,
        j.display_id,
        j.customer_name,
        j.vehicle_brand,
        j.vehicle_color,
        j.vehicle_plate,
        GROUP_CONCAT(DISTINCT CONCAT(s.fname, ' ', s.lname) SEPARATOR ', ') AS mechanic_names
    FROM
        jobs j
    LEFT JOIN
        job_staff js ON j.id = js.job_id
    LEFT JOIN
        staff s ON js.staff_id = s.id
    WHERE
        j.display_id = ? AND j.status = 'Completed'
    GROUP BY
        j.id;
    ";

    $stmtJob = $conn->prepare($sqlJob);
    $stmtJob->bind_param('s', $jobDisplayId);
    $stmtJob->execute();
    $resultJob = $stmtJob->get_result();

    if ($jobDetails = $resultJob->fetch_assoc()) {
        $jobId = $jobDetails['id'];

        // Corrected: Fetch services for the job using the actual job_id
        $sqlServices = "
        SELECT
            s.name,
            js.cost
        FROM
            job_service js
        LEFT JOIN
            services s ON js.service_id = s.id
        WHERE
            js.job_id = ?;
        ";
        $stmtServices = $conn->prepare($sqlServices);
        $stmtServices->bind_param('i', $jobId);
        $stmtServices->execute();
        $resultServices = $stmtServices->get_result();
        
        $services = [];
        $servicesCost = 0;
        while ($service = $resultServices->fetch_assoc()) {
            $services[] = ['name' => $service['name'], 'cost' => (float)$service['cost']];
            $servicesCost += (float)$service['cost'];
        }

        $stmtServices->close();
        
        // Modification: Fetch the invoice ID and the latest payment details including payment_date
        $sqlCheckInvoice = "
        SELECT 
            i.id,
            i.total_amount,
            p.amount AS amount_paid,
            p.change_given,
            p.payment_method,
            p.transaction_id,
            p.payment_date
        FROM invoices i
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.job_id = ?
        ORDER BY p.payment_date DESC LIMIT 1;
        ";
        $stmtCheckInvoice = $conn->prepare($sqlCheckInvoice);
        $stmtCheckInvoice->bind_param('i', $jobId);
        $stmtCheckInvoice->execute();
        $resultCheckInvoice = $stmtCheckInvoice->get_result();
        $invoiceDetails = $resultCheckInvoice->fetch_assoc();
        $stmtCheckInvoice->close();

        // New: Fetch additional costs from the database
        $additionalCosts = [];
        if ($invoiceDetails) {
            $sqlAdditionalCosts = "
            SELECT
                description,
                amount
            FROM
                invoice_additional_costs
            WHERE
                invoice_id = ?;
            ";
            $stmtAdditionalCosts = $conn->prepare($sqlAdditionalCosts);
            $stmtAdditionalCosts->bind_param('i', $invoiceDetails['id']);
            $stmtAdditionalCosts->execute();
            $resultAdditionalCosts = $stmtAdditionalCosts->get_result();
            while ($cost = $resultAdditionalCosts->fetch_assoc()) {
                $additionalCosts[] = ['reason' => $cost['description'], 'cost' => (float)$cost['amount']];
            }
            $stmtAdditionalCosts->close();
        }


        $response = [
            'success' => true,
            'message' => 'Job details fetched successfully.',
            'job' => [
                'id' => $jobDetails['id'],
                'job_id' => $jobDetails['display_id'],
                'customer' => [
                    'name' => $jobDetails['customer_name'],
                    'vehicle' => [
                        'brand' => $jobDetails['vehicle_brand'],
                        'color' => $jobDetails['vehicle_color'],
                        'plate' => $jobDetails['vehicle_plate']
                    ]
                ],
                'mechanic' => [
                    'name' => $jobDetails['mechanic_names']
                ],
                'services' => $services,
                'services_cost' => $servicesCost,
                'additional_costs' => $additionalCosts,
                'invoice_id' => $invoiceDetails['id'] ?? null,
                'total_cost' => $invoiceDetails['total_amount'] ?? null,
                'amount_paid' => $invoiceDetails['amount_paid'] ?? null,
                'change_given' => $invoiceDetails['change_given'] ?? null,
                'payment_method' => $invoiceDetails['payment_method'] ?? null,
                'transaction_id' => $invoiceDetails['transaction_id'] ?? null,
                'payment_date' => $invoiceDetails['payment_date'] ?? null
            ]
        ];
    }

    $stmtJob->close();
}

$conn->close();
echo json_encode($response);
?>