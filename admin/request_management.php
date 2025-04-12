<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ob_start();

require '../config/function.php';
session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $response = ['status' => 'error', 'message' => 'User not logged in. Please log in to perform this action.'];
    echo json_encode($response);
    exit;
}

header('Content-Type: application/json');
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

$response = ['status' => 'error', 'message' => 'Invalid action.'];

try {
    switch ($action) {
        case 'approve':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE requests SET status = 'In Process', updated_at = NOW() WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Approve Request', "Request ID: $id", "Status changed to In Process");
                $_SESSION['message'] = "Request ID $id approved successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response['message'] = 'Failed to approve request or request is not in Pending status.';
            }
            break;

        case 'bulk_approve':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response['message'] = 'No valid requests selected.';
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE requests SET status = 'In Process', updated_at = NOW() WHERE id IN ($placeholders) AND status = 'Pending'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Approve Requests', "Count: $affected", "Request IDs: $request_ids");
                    $_SESSION['message'] = "$affected request(s) approved successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response['message'] = 'No Pending requests were approved.';
                }
            } else {
                throw new Exception("Failed to bulk approve requests: " . $stmt->error);
            }
            break;

        case 'reject':
            $id = (int)$_POST['id'];
            $reason = validate($_POST['rejection_reason']);
            if (empty($reason)) {
                $response['message'] = 'Rejection reason is required.';
                break;
            }
            $stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
            $stmt->bind_param("si", $reason, $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Reject Request', "Request ID: $id", "Reason: $reason");
                $_SESSION['message'] = "Request ID $id rejected successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response['message'] = 'Failed to reject request or request is not in Pending status.';
            }
            break;

        case 'bulk_reject':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            $reason = validate($_POST['rejection_reason']);
            if (empty($ids)) {
                $response['message'] = 'No valid requests selected.';
                break;
            }
            if (empty($reason)) {
                $response['message'] = 'Rejection reason is required.';
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id IN ($placeholders) AND status = 'Pending'");
            $stmt->bind_param("s" . str_repeat('i', count($ids)), $reason, ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Reject Request', "Count: $affected", "Request IDs: $request_ids, Reason: $reason");
                    $_SESSION['message'] = "$affected request(s) rejected successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response['message'] = 'No Pending requests were rejected.';
                }
            } else {
                throw new Exception("Failed to bulk reject requests: " . $stmt->error);
            }
            break;

        case 'mark_ready':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', updated_at = NOW() WHERE id = ? AND status = 'In Process'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Mark Ready Request', "Request ID: $id", "Status changed to Ready to Pickup");
                $_SESSION['message'] = "Request ID $id marked as Ready to Pickup.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response['message'] = 'Failed to mark request as ready or request is not In Process.';
            }
            break;

        case 'bulk_mark_ready':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response['message'] = 'No valid requests selected.';
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', updated_at = NOW() WHERE id IN ($placeholders) AND status = 'In Process'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Mark Ready Request', "Count: $affected", "Request IDs: $request_ids");
                    $_SESSION['message'] = "$affected request(s) marked as Ready to Pickup.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response['message'] = 'No In Process requests were marked as ready.';
                }
            } else {
                throw new Exception("Failed to bulk mark requests as ready: " . $stmt->error);
            }
            break;

        case 'mark_completed':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', updated_at = NOW() WHERE id = ? AND status = 'Ready to Pickup'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Mark Completed Request', "Request ID: $id", "Status changed to Completed");
                $_SESSION['message'] = "Request ID $id marked as Completed.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response['message'] = 'Failed to mark request as completed or request is not Ready to Pickup.';
            }
            break;

        case 'bulk_mark_completed':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response['message'] = 'No valid requests selected.';
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', updated_at = NOW() WHERE id IN ($placeholders) AND status = 'Ready to Pickup'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Mark Completed Requests', "Count: $affected", "Request IDs: $request_ids");
                    $_SESSION['message'] = "$affected request(s) marked as Completed.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response['message'] = 'No Ready to Pickup requests were marked as completed.';
                }
            } else {
                throw new Exception("Failed to bulk mark requests as completed: " . $stmt->error);
            }
            break;

        case 'get':
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT r.*, CONCAT(u.firstname, ' ', u.lastname) AS student_name 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $response = ['status' => 'success', 'data' => $result->fetch_assoc()];
            } else {
                $response['message'] = 'Request not found.';
            }
            break;

        default:
            $response['message'] = 'Invalid action.';
            break;
    }
} catch (Exception $e) {
    error_log("Error in request_management.php: " . $e->getMessage());
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = 'danger';
}

ob_end_clean();
echo json_encode($response);
?>