<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ob_start();

// Include necessary files
require '../config/function.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
    redirect('../index.php', 'Please log in as an admin or registrar to perform this action.', 'warning');
    exit();
}

// Set content type header
header('Content-Type: application/json');

// Get the action parameter
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// Initialize response
$response = ['status' => 'error', 'message' => 'Invalid action.'];

try {
    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection is not established.');
    }

    switch ($action) {
        case 'get':
            $id = (int)$_GET['id'];
            // Fetch payment details
            $stmt = $conn->prepare("
                SELECT p.id, p.request_id, p.payment_method, p.amount, p.payment_status, 
                       p.description, p.payment_date 
                FROM payments p 
                WHERE p.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $payment = $result->fetch_assoc();
                // Fetch associated request details
                $stmt = $conn->prepare("
                    SELECT 
                        r.id, 
                        r.document_type, 
                        CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                        r.status, 
                        r.requested_date, 
                        u.number, 
                        u.email
                    FROM requests r 
                    JOIN users u ON r.user_id = u.id 
                    WHERE r.id = ?
                ");
                $stmt->bind_param("i", $payment['request_id']);
                $stmt->execute();
                $request_result = $stmt->get_result();
                $request = $request_result->num_rows > 0 ? $request_result->fetch_assoc() : [];
                $response = ['status' => 'success', 'payment' => $payment, 'request' => $request];
            } else {
                $response = ['status' => 'error', 'message' => 'Payment not found.'];
            }
            $stmt->close();
            break;

        case 'archive':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE payments SET archived = 1, updated_at = NOW() WHERE id = ? AND archived = 0");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Archive Payment', "Payment ID: $id", "Payment archived");
                $_SESSION['message'] = "Payment ID $id archived successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to archive payment or payment is already archived.'];
            }
            $stmt->close();
            break;

        case 'bulk_archive':
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No payments selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE payments SET archived = 1, updated_at = NOW() WHERE id IN ($placeholders) AND archived = 0");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $archived_count = $stmt->affected_rows;
            $stmt->close();
            if ($archived_count > 0) {
                logAction($conn, 'Bulk Archive Payments', "Payment IDs: " . implode(',', $ids), "Archived $archived_count payments");
                $_SESSION['message'] = "$archived_count payment(s) archived successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No payments were archived. Ensure the selected payments are not already archived.'];
            }
            break;

        case 'retrieve':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE payments SET archived = 0, updated_at = NOW() WHERE id = ? AND archived = 1");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Retrieve Payment', "Payment ID: $id", "Payment retrieved from archive");
                $_SESSION['message'] = "Payment ID $id retrieved successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to retrieve payment or payment is not archived.'];
            }
            $stmt->close();
            break;

        case 'delete':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Delete Payment', "Payment ID: $id", "Payment deleted");
                $_SESSION['message'] = "Payment ID $id deleted successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to delete payment or payment not found.'];
            }
            $stmt->close();
            break;

        case 'bulk_delete':
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No payments selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM payments WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $deleted_count = $stmt->affected_rows;
            $stmt->close();
            if ($deleted_count > 0) {
                logAction($conn, 'Bulk Delete Payments', "Payment IDs: " . implode(',', $ids), "Deleted $deleted_count payments");
                $_SESSION['message'] = "$deleted_count payment(s) deleted successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No payments were deleted.'];
            }
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action specified.'];
            break;
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
}

// Output the response
echo json_encode($response);

// Close database connection
if (isset($conn)) {
    $conn->close();
}

ob_end_flush();
?>