<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "127.0.0.1:3306";
$username = "u157619782_Jeffix";
$password = "@VMVJeffix123";
$dbname = "u157619782_vmvjeffix";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Handle Step 1: Email + Password
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $pass = $conn->real_escape_string($_POST['password']);

    // Check credentials
    $sql = "SELECT * FROM app_users WHERE email='$email' LIMIT 1"; // adjust table name to yours
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Assuming passwords are stored hashed; if plain, use ($row['password']==$pass)
        if (password_verify($pass, $row['password']) || $row['password']==$pass) {
            // Generate OTP
            $otp = rand(100000, 999999);
            $_SESSION['otp'] = $otp;
            $_SESSION['otp_expiration'] = time() + 300; // 5 minutes
            $_SESSION['email'] = $email;

            // Send OTP
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'jeffixofficial2025@gmail.com';
                $mail->Password = 'aump wlep hmnx jqks'; // App password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('jeffixofficial2025@gmail.com', 'Jeffix');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Your OTP Code';
                $mail->Body = "
                <div style='font-family: Arial; padding:20px;'>
                    <h2>Your Login OTP</h2>
                    <p>Use the following OTP to complete your login:</p>
                    <h1 style='color:#ff6600;'>$otp</h1>
                    <p>This code will expire in 5 minutes.</p>
                </div>";

                $mail->send();
                $step = 2; // go to OTP step
            } catch (Exception $e) {
                $error = "OTP could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Email not found.";
    }
}

// Handle Step 2: OTP Verification
if (isset($_POST['verify_otp'])) {
    $user_otp = $_POST['otp'];

    if (isset($_SESSION['otp']) && isset($_SESSION['otp_expiration'])) {
        if (time() <= $_SESSION['otp_expiration']) {
            if ($user_otp == $_SESSION['otp']) {
                // OTP valid
                unset($_SESSION['otp']);
                unset($_SESSION['otp_expiration']);
                header("Location: welcome.php");
                exit();
            } else {
                $error = "Invalid OTP.";
                $step = 2;
            }
        } else {
            $error = "OTP expired.";
            $step = 2;
        }
    } else {
        $error = "No OTP session found.";
    }
}

// Determine which step to show
if (!isset($step)) {
    $step = (isset($_SESSION['otp'])) ? 2 : 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login with OTP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4">Login</h3>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>

                    <?php if ($step == 1): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
                        </form>
                    <?php elseif ($step == 2): ?>
                        <form method="post">
                            <div class="mb-3">
                                <label for="otp" class="form-label">Enter OTP:</label>
                                <input type="text" class="form-control" id="otp" name="otp" required>
                            </div>
                            <button type="submit" name="verify_otp" class="btn btn-success w-100">Verify OTP</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
