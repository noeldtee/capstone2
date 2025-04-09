<?php
require 'config/function.php'; // Use $conn from dbcon.php via function.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function send_password_reset($firstname, $email, $reset_token) {
    try {
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
        $mail->Subject = 'Password Reset Request from Bulacan Polytechnic College Registrar';

        $email_template = "
            <h2>Password Reset</h2>
            <p>Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ", We received a request to reset your password. Please click the link below to reset it:</p>
            <br><br>
            <a href='http://localhost/capstone-admin/reset-password.php?token=" . urlencode($reset_token) . "'>Reset Password</a>
            <p>This link will expire in 1 hour. If you didn’t request this, ignore this email.</p>
            <p><strong>Note for Development:</strong> If you are testing locally, copy and paste this link into your browser on the machine running the local server (e.g., XAMPP/WAMP on port 80).</p>
        ";

        $mail->Body = $email_template;
        $mail->send();
    } catch (Exception $e) {
        throw new Exception("Failed to send password reset email: " . $e->getMessage());
    }
}

// Handle password reset link request
if (isset($_POST['reset_password_link'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect('password-reset.php', 'Invalid CSRF token.', 'danger');
        exit();
    }

    $email = validate($_POST['email']);

    if (empty($email)) {
        redirect('password-reset.php', 'Email is required.', 'danger');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('password-reset.php', 'Invalid email format.', 'danger');
        exit();
    }

    // Basic rate limiting: Check if a request was made in the last 5 minutes
    if (isset($_SESSION['last_password_reset_request']) && (time() - $_SESSION['last_password_reset_request']) < 300) {
        redirect('password-reset.php', 'Please wait 5 minutes before requesting another password reset link.', 'danger');
        exit();
    }

    // Check connection before query
    if (!$conn || !$conn->ping()) {
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE); // Reconnect
        if (!$conn) {
            redirect('password-reset.php', 'Database connection failed. Please try again later.', 'danger');
            exit();
        }
        mysqli_set_charset($conn, 'utf8mb4'); // Set charset after reconnect
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT firstname, email FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) == 1) {
        $row = $result->fetch_assoc();

        // Generate reset token
        $reset_token = bin2hex(random_bytes(16)); // 32-character hex token
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

        // Store reset token and expiration in the database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?");
        $stmt->bind_param("sss", $reset_token, $expires_at, $email);
        $stmt->execute();

        try {
            send_password_reset($row['firstname'], $email, $reset_token);
            // Update last request time for rate limiting
            $_SESSION['last_password_reset_request'] = time();
            redirect('password-reset.php', 'Password reset link sent successfully. Please check your inbox.', 'success');
        } catch (Exception $e) {
            redirect('password-reset.php', 'Failed to send password reset link: ' . $e->getMessage(), 'danger');
        }
    } else {
        redirect('password-reset.php', 'Email not found.', 'danger');
    }
}

// Handle password update
if (isset($_POST['password_update'])) {
    $email = validate($_POST['email']);
    $new_password = validate($_POST['new_password']);
    $confirm_password = validate($_POST['confirm_password']);
    $reset_token = validate($_POST['password_token']);

    if (empty($reset_token)) {
        redirect('password-reset.php', 'Invalid token.', 'danger');
        exit();
    }

    // Sanitize email for redirect URL to prevent XSS
    $safe_email = urlencode($email);

    // Check connection before query
    if (!$conn || !$conn->ping()) {
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE); // Reconnect
        if (!$conn) {
            redirect("reset-password.php?token=" . urlencode($reset_token) . "&email=" . $safe_email, 'Database connection failed. Please try again later.', 'danger');
            exit();
        }
        mysqli_set_charset($conn, 'utf8mb4'); // Set charset after reconnect
    }

    // Check if token exists and is valid
    $stmt = $conn->prepare("SELECT email, reset_expires_at FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $reset_token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) == 1) {
        $row = $result->fetch_assoc();

        // Check if token has expired
        $expires_at = new DateTime($row['reset_expires_at']);
        $now = new DateTime();
        if ($now > $expires_at) {
            redirect("reset-password.php?token=" . urlencode($reset_token) . "&email=" . $safe_email, 'Password reset link has expired. Please request a new link.', 'danger');
            exit();
        }

        // Only check new_password and confirm_password for emptiness, as email and token are from hidden fields
        if (empty($new_password) || empty($confirm_password)) {
            redirect("reset-password.php?token=" . urlencode($reset_token) . "&email=" . $safe_email, 'New Password and Confirm Password are required.', 'danger');
            exit();
        }

        // Validate password match
        if ($new_password !== $confirm_password) {
            redirect("reset-password.php?token=" . urlencode($reset_token) . "&email=" . $safe_email, 'Password and Confirm Password do not match.', 'danger');
            exit();
        }

        // Validate password requirements (same as register.php)
        if (strlen($new_password) < 8 || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            redirect("reset-password.php?token=" . urlencode($reset_token) . "&email=" . $safe_email, 'Password must be at least 8 characters long and include at least one number and one special character (e.g., !@#$%^&*).', 'danger');
            exit();
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE reset_token = ?");
        $stmt->bind_param("ss", $hashed_password, $reset_token);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            redirect('index.php', 'Password updated successfully. Please login.', 'success');
        } else {
            redirect("reset-password.php?token=" . urlencode($reset_token) . "&email=" . $safe_email, 'Did not update password. Something went wrong.', 'danger');
        }
    } else {
        redirect('password-reset.php', 'Invalid token.', 'danger');
    }
}

// Close database connection
if ($conn) {
    $conn->close();
}