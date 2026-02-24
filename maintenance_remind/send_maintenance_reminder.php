<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: text/html"); // Display HTML in browser
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Database connection
$servername = "127.0.0.1:3306";
$username = "u157619782_d";
$password = "@VMVJeffix123";
$dbname = "u157619782_d";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Setup timezone
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Function to send maintenance reminder email
function sendMaintenanceEmail($email, $name, $vehicle_brand, $vehicle_plate, $payment_date) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jeffixofficial2025@gmail.com';
        $mail->Password = 'pznv vfee sahn zxdr'; // Gmail App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('jeffixofficial2025@gmail.com', 'Jeffix');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Maintenance Reminder for Your $vehicle_brand";

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'><title>Maintenance Reminder</title></head>
            <body style='font-family: Arial, sans-serif; background-color:#f5f5f5; padding:20px;'>
            <table align='center' cellpadding='0' cellspacing='0' width='100%' style='max-width:500px; background-color:#fff; border-radius:10px; box-shadow:0 4px 15px rgba(0,0,0,0.1);'>
            <tr>
            <td style='background-color:#ff5722; padding:20px; text-align:center; color:#fff; font-size:22px; font-weight:bold;'>Jeffix</td>
            </tr>
            <tr>
            <td style='padding:25px; color:#333;'>
            <p style='font-size:16px;'>Hello <strong>$name</strong>,</p>
            <p style='font-size:16px;'>It's time for your vehicle's maintenance! Your last maintenance was on <strong>$payment_date</strong>.</p>
            <div style='background-color:#ff5722; color:#fff; padding:15px; border-radius:8px; margin:20px 0; text-align:center;'>
            <p style='margin:0; font-size:18px; font-weight:bold;'>$vehicle_brand</p>
            <p style='margin:5px 0 0 0;'>Plate: $vehicle_plate</p>
            </div>
            <p style='font-size:16px;'>To keep your vehicle running smoothly, please schedule your next service as soon as possible.</p>
            <p style='margin-top:20px; font-size:14px; color:#777;'>— VMV Virtual Mechanic System</p>
            </td>
            </tr>
            <tr>
            <td style='background-color:#f5f5f5; padding:15px; text-align:center; font-size:12px; color:#777;'>
            This is an automated maintenance reminder. Please do not reply.
            </td>
            </tr>
            </table>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// --- Fetch emails from DB ---
$sql = "
SELECT 
    a.email, 
    j.customer_name,
    j.vehicle_plate, 
    j.vehicle_brand, 
    p.payment_date
FROM jobs j
INNER JOIN invoices i ON i.job_id = j.id
INNER JOIN payments p ON p.invoice_id = i.id
INNER JOIN appusers a ON a.id = j.au_id
WHERE p.payment_date <= CURDATE()
";

$result = $conn->query($sql);
if (!$result) {
    die("Database query failed: " . $conn->error);
}

$emails_seen = [];
$emails_sent = [];

while ($row = $result->fetch_assoc()) {
    $email = $row['email'];
    $emails_seen[] = $email;

    $name = $row['customer_name'];
    $vehicle_brand = $row['vehicle_brand'];
    $vehicle_plate = $row['vehicle_plate'];
    $payment_date = $row['payment_date'];

    $six_month_date = date('Y-m-d', strtotime('+6 months', strtotime($payment_date)));

    if ($six_month_date === $today) {
        if (sendMaintenanceEmail($email, $name, $vehicle_brand, $vehicle_plate, $payment_date)) {
            $emails_sent[] = $email;
        }
    }
}

// --- Display results in browser ---
echo "<h1>Maintenance Reminder Script</h1>";
echo "<p>Today: <strong>$today</strong></p>";

echo "<h2>All Emails Seen in DB:</h2>";
if (!empty($emails_seen)) {
    echo "<ul>";
    foreach ($emails_seen as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No emails found in DB.</p>";
}

echo "<h2>Emails Sent Today:</h2>";
if (!empty($emails_sent)) {
    echo "<ul>";
    foreach ($emails_sent as $e) {
        echo "<li>" . htmlspecialchars($e) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No emails sent today.</p>";
}

$conn->close();
?>
