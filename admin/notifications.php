<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

require '../config/function.php';
session_start();

header('Content-Type: application/json');

// Restrict to admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'fetch':
        // Fetch unread notifications
        $stmt = $conn->prepare("
            SELECT n.id, n.user_id, n.message, n.link, n.created_at, u.firstname, u.lastname 
            FROM notifications n 
            LEFT JOIN users u ON n.user_id = u.id 
            WHERE n.is_read = 0 
            ORDER BY n.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'id' => $row['id'],
                'message' => $row['message'], // E.g., "New user verified: John Doe"
                'link' => $row['link'], // E.g., "view-user.php?id=123"
                'created_at' => date('M d, Y H:i', strtotime($row['created_at']))
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
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'All notifications cleared']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

ob_end_clean();
?>