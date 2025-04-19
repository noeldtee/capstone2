<?php
session_start();
require '../config/dbcon.php'; // Path from /capstone-admin/admin/ to /capstone-admin/config/

header('Content-Type: application/json');

$admin_id = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? '';

$response = ['status' => 'error', 'message' => '', 'notifications' => []];

if (!$admin_id) {
    $response['message'] = 'Admin not logged in.';
    error_log('Admin not logged in: No user_id in session. Session data: ' . json_encode($_SESSION));
    echo json_encode($response);
    exit;
}

try {
    switch ($action) {
        case 'fetch':
            $stmt = $conn->prepare("SELECT * FROM admin_notifications WHERE admin_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
            if (!$stmt) {
                $response['message'] = 'Failed to prepare statement: ' . $conn->error;
                error_log("Prepare failed in fetch: " . $conn->error);
                echo json_encode($response);
                exit;
            }
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $notifications = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response['status'] = 'success';
            $response['notifications'] = $notifications;
            error_log("Fetched notifications for admin $admin_id: " . json_encode($notifications));
            break;

        case 'mark_read':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $response['message'] = 'Invalid notification ID.';
                error_log("Invalid notification ID: $id");
                break;
            }

            $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ? AND admin_id = ? AND is_read = 0");
            if (!$stmt) {
                $response['message'] = 'Failed to prepare statement: ' . $conn->error;
                error_log("Prepare failed in mark_read: " . $conn->error);
                echo json_encode($response);
                exit;
            }
            $stmt->bind_param("ii", $id, $admin_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Notification marked as read.';
                    error_log("Notification $id marked as read for admin $admin_id");
                } else {
                    $response['message'] = 'Notification not found or already marked as read.';
                    error_log("Notification $id not found or already read for admin $admin_id");
                }
            } else {
                $response['message'] = 'Failed to mark notification as read: ' . $conn->error;
                error_log("Failed to mark notification $id as read: " . $conn->error);
            }
            $stmt->close();
            break;

        case 'clear_all':
            $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0");
            if (!$stmt) {
                $response['message'] = 'Failed to prepare statement: ' . $conn->error;
                error_log("Prepare failed in clear_all: " . $conn->error);
                echo json_encode($response);
                exit;
            }
            $stmt->bind_param("i", $admin_id);
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'All notifications cleared.';
                error_log("All notifications cleared for admin $admin_id");
            } else {
                $response['message'] = 'Failed to clear notifications: ' . $conn->error;
                error_log("Failed to clear notifications for admin $admin_id: " . $conn->error);
            }
            $stmt->close();
            break;

        default:
            $response['message'] = 'Invalid action.';
            error_log("Invalid action: $action");
            break;
    }
} catch (Exception $e) {
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    error_log("Error in notifications.php: " . $e->getMessage());
}

echo json_encode($response);
?>