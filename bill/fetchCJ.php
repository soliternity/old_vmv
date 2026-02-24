<?php
header('Content-Type: application/json');
require_once '../dbconn.php';

$response = [
    'success' => false,
    'message' => 'An error occurred.'
];

$sql = "
SELECT
    j.id,
    j.display_id,
    j.created_at,
    j.customer_name,
    j.vehicle_brand,
    j.vehicle_color,
    j.vehicle_plate,
    s.fname AS mechanic_fname,
    s.lname AS mechanic_lname
FROM
    jobs j
LEFT JOIN
    job_staff js ON j.id = js.job_id
LEFT JOIN
    staff s ON js.staff_id = s.id
WHERE
    j.status = 'Completed'
GROUP BY
    j.id
ORDER BY
    j.created_at DESC;
";

$result = $conn->query($sql);

if ($result) {
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = [
            'id' => $row['id'],
            'job_id' => $row['display_id'],
            'date_created' => date('Y-m-d', strtotime($row['created_at'])),
            'customer' => [
                'name' => $row['customer_name'],
                'vehicle' => [
                    'brand' => $row['vehicle_brand'],
                    'color' => $row['vehicle_color'],
                    'plate' => $row['vehicle_plate']
                ]
            ],
            'mechanic' => [
                'name' => trim($row['mechanic_fname'] . ' ' . $row['mechanic_lname'])
            ]
        ];
    }
    $response['success'] = true;
    $response['message'] = 'Completed jobs fetched successfully.';
    $response['jobs'] = $jobs;
} else {
    $response['message'] = 'Query failed: ' . $conn->error;
}

$conn->close();
echo json_encode($response);
?>