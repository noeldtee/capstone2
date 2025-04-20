<?php
require 'config/function.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (isset($_POST['resend_email_verify_btn'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect('resend_verification.php', 'Invalid CSRF token.', 'danger');
        exit();
    }

    $email = validate($_POST['email']);

    if (empty($email)) {
        redirect('resend_verification.php', 'Email is required.', 'danger');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('resend_verification.php', 'Invalid email format.', 'danger');
        exit();
    }

    // Basic rate limiting: Check if a request was made in the last 5 minutes
    if (isset($_SESSION['last_verification_request']) && (time() - $_SESSION['last_verification_request']) < 300) {
        redirect('resend_verification.php', 'Please wait 5 minutes before requesting another verification email.', 'danger');
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

        // Generate new verification token and set expiration (24 hours)
        $verify_token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Update token and expiration in the database
        $stmt = $conn->prepare("UPDATE users SET verify_token = ?, verify_expires_at = ? WHERE email = ?");
        $stmt->bind_param("sss", $verify_token, $expires_at, $email);
        $stmt->execute();

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

            $mail->setFrom('bpcregistrar75@gmail.com', 'Bulacan Polytechnic College Registrar');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Resend Email Verification from Bulacan Polytechnic College Registrar';

            $email_template = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <!-- Header -->
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #2e7d32; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                <img src="https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/assets/images/logo.png" alt="BPC Logo" width="80" height="76" style="display: block; margin: 0 auto;">
                <h2 style="color: #ffffff; margin: 10px 0 0; font-size: 24px;">Bulacan Polytechnic College Registrar</h2>
            </td>
        </tr>
        <!-- Body -->
        <tr>
            <td style="padding: 30px; text-align: center;">
                <h3 style="color: #2e7d32; margin: 0 0 15px; font-size: 20px;">Email Verification</h3>
                <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                    Hi ' . htmlspecialchars($row['firstname'], ENT_QUOTES, 'UTF-8') . ',<br>
                    Please click the button below to verify your email address. This link will expire in 24 hours.
                </p>
                <a href="http://localhost/capstone-admin/verify-email.php?token=' . urlencode($verify_token) . '" style="display: inline-block; padding: 12px 24px; background-color: #2e7d32; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; border-radius: 5px; margin: 20px 0;">Verify Email</a>
                <p style="color: #666666; font-size: 14px; margin: 20px 0 0;">
                    If the button above doesn’t work, copy and paste this link into your browser:<br>
                    <a href="http://localhost/capstone-admin/verify-email.php?token=' . urlencode($verify_token) . '" style="color: #2e7d32; text-decoration: underline;">http://localhost/capstone-admin/verify-email.php?token=' . urlencode($verify_token) . '</a>
                </p>
                <p style="color: #666666; font-size: 14px; margin: 20px 0 0;">
                    If you did not request this verification, please ignore this email or contact support.
                </p>
            </td>
        </tr>
        <!-- Footer -->
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #f4f4f4; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                <p style="color: #666666; font-size: 12px; margin: 0;">
                    © ' . date('Y') . ' Bulacan Polytechnic College. All rights reserved.<br>
                    For support, contact us at <a href="mailto:support@bpc.edu" style="color: #2e7d32; text-decoration: underline;">support@bpc.edu</a>.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
';

            $mail->Body = $email_template;
            $mail->AltBody = "Hi " . htmlspecialchars($row['firstname'], ENT_QUOTES, 'UTF-8') . ",\n\nPlease click the link below to verify your email address (expires in 24 hours):\n\nhttp://localhost/capstone-admin/verify-email.php?token=" . urlencode($verify_token) . "\n\nIf you did not request this verification, please ignore this email or contact support.\n\nBest regards,\nBulacan Polytechnic College Registrar";
            $mail->send();

            // Update last request time for rate limiting
            $_SESSION['last_verification_request'] = time();

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
?>