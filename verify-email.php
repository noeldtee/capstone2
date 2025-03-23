<?php
session_start();
require 'config/function.php'; // Use $conn from dbcon.php via function.php

if (isset($_GET['token'])) {
    $token = validate($_GET['token']); // Sanitize token

    // Use prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT verify_token, verify_status FROM users WHERE verify_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) > 0) {
        $row = $result->fetch_assoc();
        
        if ($row['verify_status'] == 0) {
            // Update verify_status to 1 and clear verify_token
            $stmt = $conn->prepare("UPDATE users SET verify_status = 1, verify_token = NULL WHERE verify_token = ? LIMIT 1");
            $stmt->bind_param("s", $token);
            $update_result = $stmt->execute();

            if ($update_result) {
                echo "<h2>Email Verified</h2><p>Your email has been verified successfully. <a href='index.php'>Click here to login</a>.</p>";
            } else {
                redirect('index.php', 'Email not verified. Please try again.', 'danger');
            }
        } else {
            redirect('index.php', 'Email already verified. Please login.', 'info');
        }
    } else {
        redirect('index.php', 'This token does not exist.', 'danger');
    }
} else {
    redirect('index.php', 'Please verify your email first.', 'warning');
}

// Close database connection
$conn->close();