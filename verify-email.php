<?php
require 'config/function.php';
session_start();

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    redirect('index.php', 'Invalid verification link.', 'danger');
    exit;
}

// Find user by token
$stmt = $conn->prepare("SELECT id, firstname, lastname, email FROM users WHERE verify_token = ? AND verify_status = 0 LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('index.php', 'Invalid or already verified token.', 'danger');
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$firstname = $user['firstname'];
$lastname = $user['lastname'];

// Update verify_status and clear token
$stmt = $conn->prepare("UPDATE users SET verify_status = 1, verify_token = NULL WHERE id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    // Insert notification for admin
    $message = "New user verified: {$firstname} {$lastname}";
    $link = "view-user.php?id={$user_id}"; // Adjust to your actual user view page
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $user_id, $message, $link);
    $stmt->execute();

    // Log to action_logs for auditing
    logAction($conn, 'verify_email', 'user', "User ID {$user_id} verified email", $_SERVER['REMOTE_ADDR']);

    redirect('index.php', 'Email verified successfully! You can now log in.', 'success');
} else {
    redirect('index.php', 'Verification failed. Please try again.', 'danger');
}

$stmt->close();
closeConnection();
?>