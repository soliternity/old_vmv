<?php
// This file is included by addPay.php. All variables are available here.

$receipt_date = date('Y-m-d H:i:s');
$job_display_id = $data['job_display_id'];
$customer_name = $data['customer_name'];
$vehicle_brand = $data['vehicle_details']['brand'];
$vehicle_color = $data['vehicle_details']['color'];
$vehicle_plate = $data['vehicle_details']['plate'];
$mechanic_name = $data['mechanic_name'];
$services = $data['services'];
$additional_costs = $data['additional_costs'];
$total_cost = $data['total_cost'];
$amount_paid = $data['amount_paid'];
$change_given = $data['change_given'];
$payment_method = $data['payment_method'];
$invoice_id = $invoice_id; // from addPay.php
$invoice_number = $invoice_number;
$transaction_id = $transaction_id; // from addPay.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JEFFIX - Official Receipt</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; line-height: 1.6; max-width: 600px; margin: auto; padding: 20px; }
        .b-receipt-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .b-receipt-header h1 { margin: 0; font-size: 1.5rem; }
        .b-receipt-details, .b-receipt-costs { margin-bottom: 20px; }
        .b-receipt-details p, .b-receipt-costs p { margin: 5px 0; }
        .b-receipt-costs h4, .b-receipt-total h4 { margin-top: 0; }
        .b-receipt-total { text-align: right; font-size: 1.2rem; font-weight: bold; border-top: 2px solid #000; padding-top: 10px; }
        .b-receipt-footer { text-align: center; margin-top: 40px; font-size: 0.9rem; }
        .b-receipt-services-list, .b-receipt-additional-list { list-style-type: none; padding: 0; }
        .b-receipt-services-list li, .b-receipt-additional-list li { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .b-receipt-additional-list li { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .b-receipt-total p { display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="b-receipt-container">
        <div class="b-receipt-header">
            <h1>JEFFIX</h1>
            <p><strong>Official Receipt</strong></p>
            <p>ST. Anne Deca Homes, Marilao, Philippines, 3019</p>
            <p>jeffixofficial@gmail.com | (+63) 927 987 5521</p>
        </div>

        <div class="b-receipt-details">
            <p><strong>Payment Date:</strong> <?php echo date('Y-m-d'); ?> | <strong>Time:</strong> <?php echo date('H:i:s'); ?></p>
            <p><strong>Job ID:</strong> <?php echo htmlspecialchars($job_display_id); ?></p>
            <p><strong>Invoice Number:</strong> <?php echo htmlspecialchars($invoice_number); ?></p>
            <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transaction_id); ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
            <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($vehicle_brand . ' ' . $vehicle_color . ' (' . $vehicle_plate . ')'); ?></p>
            <p><strong>Mechanic:</strong> <?php echo htmlspecialchars($mechanic_name); ?></p>
        </div>

        <div class="b-receipt-costs">
            <h4>Services Rendered</h4>
            <ul class="b-receipt-services-list">
                <?php foreach ($services as $service): ?>
                    <li><span><?php echo htmlspecialchars($service['name']); ?></span><span>₱<?php echo number_format($service['cost'], 2); ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if (!empty($additional_costs)): ?>
            <div class="b-receipt-costs">
                <h4>Additional Costs</h4>
                <ul class="b-receipt-additional-list">
                    <?php foreach ($additional_costs as $cost): ?>
                        <li><span><?php echo htmlspecialchars($cost['reason']); ?></span><span>₱<?php echo number_format($cost['cost'], 2); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="b-receipt-total">
            <p><span>Total:</span><span>₱<?php echo number_format($total_cost, 2); ?></span></p>
            <p><span>Amount Paid:</span><span>₱<?php echo number_format($amount_paid, 2); ?></span></p>
            <p><span>Change:</span><span>₱<?php echo number_format($change_given, 2); ?></span></p>
            <p><span>Payment Method:</span><span><?php echo htmlspecialchars(ucfirst($payment_method)); ?></span></p>
        </div>

        <div class="b-receipt-footer">
            <p>Thank you for your business!</p>
            <p>For inquiries, please call our hotline.</p>
        </div>
    </div>
</body>
</html>