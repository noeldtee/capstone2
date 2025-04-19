<?php
session_start();
include('../config/dbcon.php');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'unread_count' => 0];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

if ($notification_id <= 0) {
    $response['message'] = 'Invalid notification ID.';
    echo json_encode($response);
    exit;
}

try {
    // Mark the notification as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $notification_id, $user_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Notification marked as read.';
        } else {
            $response['message'] = 'Notification not found or already marked as read.';
        }
    } else {
        $response['message'] = 'Failed to mark notification as read: ' . $conn->error;
    }
    $stmt->close();

    // Fetch the updated unread count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $response['unread_count'] = $row['unread_count'];
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

echo json_encode($response);