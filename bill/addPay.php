<?php
// Add these lines to the top for debugging purposes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../dbconn.php';

$response = [
    'success' => false,
    'message' => 'An error occurred during payment processing.'
];

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
    echo json_encode($response);
    exit();
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);

if (
    !isset($data['job_id']) ||
    !isset($data['job_display_id']) ||
    !isset($data['total_cost']) ||
    !isset($data['amount_paid']) ||
    !isset($data['change_given']) ||
    !isset($data['payment_method']) ||
    !isset($data['customer_name']) ||
    !isset($data['vehicle_details']) ||
    !isset($data['mechanic_name']) ||
    !isset($data['services'])
) {
    $response['message'] = 'Required fields are missing.';
    echo json_encode($response);
    exit();
}

$job_id = (int)$data['job_id'];
$job_display_id = $data['job_display_id'];
$total_cost = (float)$data['total_cost'];
$amount_paid = (float)$data['amount_paid'];
$change_given = (float)$data['change_given'];
$payment_method = $conn->real_escape_string($data['payment_method']);
$additional_costs = isset($data['additional_costs']) ? $data['additional_costs'] : [];
$transaction_id = 'TXN-' . time() . '-' . uniqid();

// Capture details from JS for the receipt
$customer_name = $data['customer_name'];
$vehicle_details = $data['vehicle_details'];
$mechanic_name = $data['mechanic_name'];
$services = $data['services'];

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // Check if an invoice for this job already exists
    $sqlCheckInvoice = "SELECT id, invoice_number FROM invoices WHERE job_id = ?";
    $stmtCheckInvoice = $conn->prepare($sqlCheckInvoice);
    $stmtCheckInvoice->bind_param('i', $job_id);
    $stmtCheckInvoice->execute();
    $resultCheckInvoice = $stmtCheckInvoice->get_result();
    
    if ($existingInvoice = $resultCheckInvoice->fetch_assoc()) {
        $invoice_id = $existingInvoice['id'];
        $invoice_number = $existingInvoice['invoice_number'];
        $sqlUpdateInvoice = "UPDATE invoices SET total_amount = ? WHERE id = ?";
        $stmtUpdateInvoice = $conn->prepare($sqlUpdateInvoice);
        $stmtUpdateInvoice->bind_param('di', $total_cost, $invoice_id);
        $stmtUpdateInvoice->execute();
        $stmtUpdateInvoice->close();
    } else {
        $invoice_number = 'INV-' . time();
        $sqlInvoice = "INSERT INTO invoices (invoice_number, job_id, total_amount) VALUES (?, ?, ?)";
        $stmtInvoice = $conn->prepare($sqlInvoice);
        $stmtInvoice->bind_param('sid', $invoice_number, $job_id, $total_cost);
        $stmtInvoice->execute();
        $invoice_id = $conn->insert_id;
        $stmtInvoice->close();
    }
    $stmtCheckInvoice->close();

    // REMOVED: Logic to insert additional costs into invoice_additional_costs table.
    // The $additional_costs array remains available for the receipt generation below.
    
    // Record the payment and apply +8 hour offset
    $sqlPayment = "INSERT INTO payments (invoice_id, payment_method, amount, transaction_id, change_given, payment_date) VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL 8 HOUR)";
    $stmtPayment = $conn->prepare($sqlPayment);
    $stmtPayment->bind_param('isdsd', $invoice_id, $payment_method, $amount_paid, $transaction_id, $change_given);
    $stmtPayment->execute();
    $stmtPayment->close();

    // Update job status to 'Paid'
    $sqlJobStatus = "UPDATE jobs SET status = 'Paid' WHERE id = ?";
    $stmtJobStatus = $conn->prepare($sqlJobStatus);
    $stmtJobStatus->bind_param('i', $job_id);
    $stmtJobStatus->execute();
    $stmtJobStatus->close();

    // Commit the transaction
    $conn->commit();

    // Generate and Save the Receipt HTML
    $receipt_filename = "receipt-" . $transaction_id . ".html";
    $receipt_filepath = __DIR__ . "/receipts/" . $receipt_filename;
    
    // Generate the HTML content using the template file
    ob_start();
    include 'receipt.php';
    $receipt_html = ob_get_clean();
    
    // Save the file
    file_put_contents($receipt_filepath, $receipt_html);

    $response['success'] = true;
    $response['message'] = 'Payment processed successfully.';
    $response['invoice_id'] = $invoice_id;
    $response['transaction_id'] = $transaction_id;
    $response['receipt_url'] = "../../b/bill/receipts/" . $receipt_filename;
    $response['invoice_number'] = $invoice_number; // Add invoice number to response

} catch (Exception $e) {
    // Rollback the transaction on error
    $conn->rollback();
    $response['message'] = 'Transaction failed: ' . $e->getMessage();
    error_log($e->getMessage());
}

$conn->close();
echo json_encode($response);
?>  