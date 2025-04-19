<?php
session_start();
include('../config/dbcon.php'); // Fixed path to match your folder structure

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Notifications cleared successfully.';
    } else {
        $response['message'] = 'Failed to clear notifications: ' . $conn->error;
    }
    $stmt->close();
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
}

echo json_encode($response);