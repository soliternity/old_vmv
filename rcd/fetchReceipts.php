<?php
header('Content-Type: application/json');

require_once '../dbconn.php';

// SQL query to fetch transaction data
$sql = "
SELECT
    p.transaction_id,
    i.invoice_number,
    j.customer_name,
    CONCAT(j.vehicle_brand, ' (', j.vehicle_plate, ')') AS vehicle,
    p.payment_date,
    p.amount AS total_cost
FROM
    payments p
JOIN
    invoices i ON p.invoice_id = i.id
JOIN
    jobs j ON i.job_id = j.id
ORDER BY
    p.payment_date DESC;
";

$result = $conn->query($sql);

$transactions = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

echo json_encode($transactions);

$conn->close();
?>