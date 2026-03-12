<?php
session_start(); // Start the session at the beginning
include "../../conn.php"; // Include your database connection

// PHPMailer library for sending email
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php'; // Include PHPMailer autoloader

$response = []; // Initialize response array

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get email from POST
    $email = isset($_POST['emailAddress']) ? $_POST['emailAddress'] : '';

    // 🟨 Handle empty or null email
    if (empty($email) || $email === 'null' || trim($email) === '') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email address is empty. Please update your profile to proceed.'
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email address.'
        ]);
        exit;
    }

    // 🔐 Generate OTP
    function generateVerificationCode() {
        return substr(number_format(time() * rand(), 0, '', ''), 0, 6);
    }

    // Create new OTP or regenerate
    if (empty($_SESSION['otp'])) {
        $verificationCode = generateVerificationCode();
        $_SESSION['otp'] = $verificationCode;
    } else {
        unset($_SESSION['otp']);
        $verificationCode = generateVerificationCode();
        $_SESSION['otp'] = $verificationCode;
    }

    // 📧 Send OTP using PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no.repyltesting@gmail.com'; // Replace with your email
        $mail->Password = 'ufrf wclh fuqy zawp'; // Replace with your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('no.repyltesting@gmail.com', 'Smart Travel');
        $mail->addAddress($email, "Recipient");

        $mail->isHTML(true);
        $mail->Subject = 'Agent One-Time-Password Verification for Password Change';
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; padding: 20px 0px 10px 0px; background-color: #fff; line-height: 1.6; text-align: left;">
            <img src="https://i.postimg.cc/7hRTGpt1/SMART-LOGO-2-2.png" alt="Smart Travel Logo" style="width: 260px; height: 45px; margin-bottom: 10px;">
            <p style="font-size: 1em;">Hi,</p>
            <p>Here is your OTP needed for password change:</p>
            <h2 style="padding: 10px; background-color: #333; color: #fff; border-radius: 5px; letter-spacing: 5px; width: 90px;">' . $verificationCode . '</h2>
            <p>This OTP is valid for 10 minutes. Do not share it with anyone.</p>
            <p style="font-size: 1.2em;">Thank you, <br/> Smart Travel </p>
            <hr style="border-top: 1px solid #eee;"/>
            <p style="font-size: 0.9em; color: #999;">If this is not for you, please ignore this email or contact support.</p>
        </div>';

        $mail->send();

        echo json_encode([
            'status' => 'success',
            'message' => 'OTP has been sent to your email address.',
            'otp' => $_SESSION['otp'] // Optional: remove this in production
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo
        ]);
    }
}
?>
