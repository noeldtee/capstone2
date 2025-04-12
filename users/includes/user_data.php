<?php
// includes/user_data.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection is available
if (!isset($conn)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/db.php'; // Adjust path as needed
}

// Fetch the logged-in user's details
$user_id = $_SESSION['user_id'] ?? null;
$user = [];
$notification_count = 0;
$notifications = [];
$profile_image = '';

if ($user_id) {
    $stmt = $conn->prepare("SELECT firstname, profile FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch unread notifications for the student
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $notification_count = count($notifications);
    $stmt->close();
}

// Base URL for profile images (adjust based on your project structure)
$base_url = '/'; // Adjust this to your project's base URL (e.g., '/your_project/')
$default_profile = $base_url . '/assets/images/default_profile.png';
$profile_image = $user['profile'] ? $base_url . ltrim($user['profile'], '/') : $default_profile;
?>