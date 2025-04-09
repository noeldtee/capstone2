<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require '../config/function.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'fetch':
        // Fetch unread notifications (user_id NULL for system-wide, or specific to admin)
        $stmt = $conn->prepare("SELECT id, message, link, created_at FROM notifications WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?) ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'],
                'message' => $row['message'],
                'link' => $row['link'],
                'created_at' => $row['created_at']
            ];
        }
        echo json_encode(['status' => 'success', 'notifications' => $notifications]);
        break;

    case 'mark_read':
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['status' => 'success', 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid notification ID']);
        }
        break;

    case 'clear_all':
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (user_id IS NULL OR user_id = ?)");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'All notifications cleared']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

ob_end_clean();
?>