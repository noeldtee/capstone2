<?php
session_start();
include('../config/database.php'); // Adjust path as needed

header('Content-Type: application/json');

$response = ['success' => false];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $response['success'] = true;
    }
    $stmt->close();
}

echo json_encode($response);
?>