<?php
require 'config/function.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (isset($_POST['resend_email_verify_btn'])) {
    $email = validate($_POST['email']);

    if (empty($email)) {
        redirect('resend_verification.php', 'Email is required.', 'danger');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('resend_verification.php', 'Invalid email format.', 'danger');
        exit();
    }

    // Check if email exists and is unverified
    $stmt = $conn->prepare("SELECT firstname, verify_status, verify_token FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) == 1) {
        $row = $result->fetch_assoc();

        if ($row['verify_status'] == 1) {
            redirect('resend_verification.php', 'Email already verified. Please login.', 'info');
            exit();
        }

        // Generate or reuse verification token
        $verify_token = $row['verify_token'] ?? bin2hex(random_bytes(16));
        if (empty($row['verify_token'])) {
            $stmt = $conn->prepare("UPDATE users SET verify_token = ? WHERE email = ?");
            $stmt->bind_param("ss", $verify_token, $email);
            $stmt->execute();
        }

        try {
            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            // $mail->SMTPDebug = 2; // Uncomment for debugging
            $mail->isSMTP();
            $mail->SMTPAuth = true;

            $mail->Host = 'smtp.gmail.com';
            $mail->Username = 'bpcregistrar75@gmail.com';
            $mail->Password = 'nkei hmzy qpwn wzch'; // Use app-specific password

            $mail->SMTPSecure = "tls";
            $mail->Port = 587;

            $mail->setFrom('bpcregistrar75@gmail.com', $row['firstname']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Resend Email Verification from Bulacan Polytechnic College Registrar';

            $email_template = "
            <h2>Email Verification</h2>
            <p>Hi {$row['firstname']}, Please click the link below to verify your email address.</p>
            <br><br>
            <a href='http://localhost/capstone-admin/verify-email.php?token={$verify_token}'>Verify Email</a>
            ";

            $mail->Body = htmlspecialchars($email_template, ENT_QUOTES, 'UTF-8');
            $mail->send();
            redirect('resend_verification.php', 'Verification email resent successfully. Please check your inbox.', 'success');
        } catch (Exception $e) {
            redirect('resend_verification.php', 'Failed to resend verification email: ' . $e->getMessage(), 'danger');
        }
    } else {
        redirect('resend_verification.php', 'Email not found.', 'danger');
    }
} else {
    redirect('resend_verification.php', 'Invalid request.', 'danger');
}

// Close database connection
$conn->close();