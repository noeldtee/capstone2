<?php
// includes/user_data.php

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection is available
if (!isset($conn)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/dbcon.php';
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
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $notification_count = count($notifications);
    $stmt->close();

    // Log for debugging
    error_log("Fetched notifications for user $user_id: " . print_r($notifications, true));
}

// Define base path for assets as an absolute URL relative to the web root
$base_path = '/capstone-admin/'; // Absolute path from web root
$default_profile = $base_path . 'assets/images/default_profile.png';

// Handle profile image
if ($user['profile']) {
    // Clean the stored profile path
    $clean_profile = preg_replace('#^\.\./#', '', $user['profile']); // Remove leading '../' if present
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/' . $clean_profile;
    $profile_image = file_exists($full_path) ? $base_path . $clean_profile : $default_profile;
} else {
    $profile_image = $default_profile;
}

// Log profile image path for debugging
error_log("Profile image path: $profile_image");
error_log("Full server path checked: $full_path");
?>