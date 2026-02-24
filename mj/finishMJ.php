<?php
session_start();
header('Content-Type: application/json');

// Include the database connection file
require_once '../dbconn.php';
require_once '../adtLogs.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$jobDisplayId = $data['job_id'] ?? null;
$serviceName = $data['service_name'] ?? null;
$hoursTaken = $data['hours_taken'] ?? null;
$partsUsed = $data['parts_used'] ?? []; // Array of parts

// Get the user ID from the session
$completedBy = $_SESSION['user_id'] ?? null;

if (empty($jobDisplayId) || empty($serviceName) || !isset($hoursTaken)) {
    die(json_encode(['status' => 'error', 'message' => 'Missing required job/service data.']));
}

if (!$completedBy) {
    die(json_encode(['status' => 'error', 'message' => 'User not authenticated.']));
}

try {
    $conn->begin_transaction();

    // 1. Get the service ID, job ID, and service pricing
    $sqlIds = "SELECT 
                s.id AS service_id, 
                s.min_cost, 
                s.max_cost, 
                s.min_hours, 
                s.max_hours,
                j.id AS job_id 
            FROM services s
            JOIN job_service js ON s.id = js.service_id
            JOIN jobs j ON js.job_id = j.id
            WHERE j.display_id = ? AND s.name = ?";
    $stmtIds = $conn->prepare($sqlIds);
    $stmtIds->bind_param("ss", $jobDisplayId, $serviceName);
    $stmtIds->execute();
    $resultIds = $stmtIds->get_result();
    $rowIds = $resultIds->fetch_assoc();
    $serviceId = $rowIds['service_id'] ?? null;
    $jobId = $rowIds['job_id'] ?? null;
    $minCost = $rowIds['min_cost'] ?? 0;
    $maxCost = $rowIds['max_cost'] ?? 0;
    $minHours = $rowIds['min_hours'] ?? 0;
    $maxHours = $rowIds['max_hours'] ?? 0;
    $stmtIds->close();

    if (!$serviceId || !$jobId) {
        $conn->rollback();
        die(json_encode(['status' => 'error', 'message' => 'Job or service not found.']));
    }

    // 2. Calculate Labor Cost
    $serviceLaborCost = 0;
    // ... (Labor cost calculation based on hours and min/max is omitted for brevity but is assumed to be here) ...
    $rangeHours = $maxHours - $minHours;
    $rangeCost = $maxCost - $minCost;
    if ($hoursTaken <= $minHours) {
        $serviceLaborCost = $minCost;
    } elseif ($hoursTaken >= $maxHours) {
        $serviceLaborCost = $maxCost;
    } elseif ($rangeHours > 0) {
        $serviceLaborCost = $minCost + (($hoursTaken - $minHours) / $rangeHours) * $rangeCost;
    } else {
        $serviceLaborCost = $minCost;
    }
    
    // 3. Update job_service with labor cost and completion details
    $sqlJobService = "UPDATE job_service
                    SET completed_at = NOW(), hours_taken = ?, cost = ?, completed_by = ?
                    WHERE job_id = ? AND service_id = ?";
    $stmtJobService = $conn->prepare($sqlJobService);
    $stmtJobService->bind_param("dsiii", $hoursTaken, $serviceLaborCost, $completedBy, $jobId, $serviceId);
    $stmtJobService->execute();
    $stmtJobService->close();
    
    // --- INVOICE MANAGEMENT: Check, Create, and Update ---

    // 4. Check for existing invoice
    $sqlCheckInvoice = "SELECT id, total_amount FROM invoices WHERE job_id = ?";
    $stmtCheckInvoice = $conn->prepare($sqlCheckInvoice);
    $stmtCheckInvoice->bind_param("i", $jobId);
    $stmtCheckInvoice->execute();
    $resultCheckInvoice = $stmtCheckInvoice->get_result();
    $invoiceRow = $resultCheckInvoice->fetch_assoc();
    $stmtCheckInvoice->close();
    
    $invoiceId = $invoiceRow['id'] ?? null;
    $invoiceNumber = 'INV' . date('Ymd') . '-' . $jobDisplayId;
    
    // 5. Create invoice if it doesn't exist
    if (!$invoiceId) {
        $sqlInvoice = "INSERT INTO invoices (invoice_number, job_id, total_amount) VALUES (?, ?, 0)";
        $stmtInvoice = $conn->prepare($sqlInvoice);
        $stmtInvoice->bind_param("si", $invoiceNumber, $jobId);
        $stmtInvoice->execute();
        $invoiceId = $conn->insert_id;
        $stmtInvoice->close();
    }
    
    // 6. Handle Parts Cost (Additional Invoice Costs)
    $totalPartsCost = 0;
    if ($invoiceId && !empty($partsUsed)) {
        $sqlAddCost = "INSERT INTO invoice_additional_costs (invoice_id, description, amount) VALUES (?, ?, ?)";
        $stmtAddCost = $conn->prepare($sqlAddCost);
        
        foreach ($partsUsed as $part) {
            $partCost = (float)($part['cost'] ?? 0);
            $partQuantity = (int)($part['quantity'] ?? 0);
            $partName = $part['name'] ?? 'Unknown Part';
            $partBrand = $part['brand'] ?? null;

            if ($partCost > 0 && $partQuantity > 0) {
                $partsTotal = $partCost * $partQuantity;
                $totalPartsCost += $partsTotal;
                
                $description = "{$partName}";
                if ($partBrand) {
                    $description .= " ({$partBrand})";
                }
                $description .= " x {$partQuantity}";

                $stmtAddCost->bind_param("isd", $invoiceId, $description, $partsTotal);
                $stmtAddCost->execute();
            }
        }
        $stmtAddCost->close();
    }

    // 7. Recalculate and Update Invoice Total
    
    // A. Get Total Labor Cost from all completed services
    $sqlTotalLabor = "SELECT SUM(cost) AS total_labor FROM job_service WHERE job_id = ?";
    $stmtTotalLabor = $conn->prepare($sqlTotalLabor);
    $stmtTotalLabor->bind_param("i", $jobId);
    $stmtTotalLabor->execute();
    $totalLabor = $stmtTotalLabor->get_result()->fetch_assoc()['total_labor'] ?? 0;
    $stmtTotalLabor->close();

    // B. Get Total Parts Cost from all additional costs (across all services)
    $sqlTotalParts = "SELECT SUM(amount) AS total_parts FROM invoice_additional_costs WHERE invoice_id = ?";
    $stmtTotalParts = $conn->prepare($sqlTotalParts);
    $stmtTotalParts->bind_param("i", $invoiceId);
    $stmtTotalParts->execute();
    $totalParts = $stmtTotalParts->get_result()->fetch_assoc()['total_parts'] ?? 0;
    $stmtTotalParts->close();

    // C. Calculate Grand Total and Update Invoice
    $totalInvoiceAmount = $totalLabor + $totalParts;

    $sqlUpdateInvoice = "UPDATE invoices SET total_amount = ? WHERE id = ?";
    $stmtUpdateInvoice = $conn->prepare($sqlUpdateInvoice);
    $stmtUpdateInvoice->bind_param("di", $totalInvoiceAmount, $invoiceId);
    $stmtUpdateInvoice->execute();
    $stmtUpdateInvoice->close();

    // --- Final Status Check (Only changes job status if ALL services are done) ---

    // 8. Check if all services for the job are now completed
    $sqlCheck = "SELECT COUNT(*) AS total_services, COUNT(completed_at) AS completed_services
                FROM job_service WHERE job_id = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $jobId);
    $stmtCheck->execute();
    $checkRow = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    // 9. If all services are completed, update the main job status
    if ($checkRow['total_services'] > 0 && $checkRow['total_services'] === $checkRow['completed_services']) {
        $sqlJobStatus = "UPDATE jobs SET status = 'Completed' WHERE id = ?";
        $stmtJobStatus = $conn->prepare($sqlJobStatus);
        $stmtJobStatus->bind_param("i", $jobId);
        $stmtJobStatus->execute();
        $stmtJobStatus->close();
    }
    
    // 10. Insert audit log
    insert_audit_log(
        $completedBy, 
        'update', 
        'Service Finished', 
        'job_service', 
        $jobId, 
        ['service_name' => $serviceName], 
        ['status' => 'Completed', 'hours' => $hoursTaken, 'labor_cost' => $serviceLaborCost, 'parts_cost' => $totalPartsCost],
        "Service '{$serviceName}' finished for Job #{$jobDisplayId}. Invoice #{$invoiceNumber} updated."
    );

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Service finished and invoice updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Transaction failed for finishMJ.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
}

$conn->close();
?>