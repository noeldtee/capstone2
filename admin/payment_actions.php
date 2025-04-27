<?php
// Start output buffering
ob_start();

// Disable error display for production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Include necessary files
require '../config/function.php';
require '../config/send_email.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    $response = ['status' => 'error', 'message' => 'Please log in to perform this action.'];
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
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
        // For payment.php: Fetch request and payment details
        case 'get':
            if (!in_array($_SESSION['role'], ['registrar', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars and cashiers can view request details.'];
                break;
            }
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("
                SELECT 
                    r.id, 
                    r.document_type, 
                    CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                    r.unit_price, 
                    r.status, 
                    r.payment_status,
                    r.payment_method,
                    r.requested_date, 
                    u.number, 
                    u.email,
                    p.id AS payment_id,
                    p.amount AS payment_amount,
                    p.payment_date,
                    p.description AS payment_description
                FROM requests r 
                JOIN users u ON r.user_id = u.id 
                LEFT JOIN payments p ON r.id = p.request_id
                WHERE r.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $response = ['status' => 'success', 'data' => $result->fetch_assoc()];
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found.'];
            }
            $stmt->close();
            break;

        // For payment.php: Mark a request as paid
        case 'mark_paid':
            if (!in_array($_SESSION['role'], ['registrar', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars and cashiers can mark payments as paid.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("
                SELECT r.document_type, r.unit_price, u.id AS user_id, u.email, u.firstname, u.lastname 
                FROM requests r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.id = ? AND r.payment_status = 'Awaiting Payment' AND r.payment_method = 'cash'
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $unit_price = $row['unit_price'];
                $user_id = $row['user_id'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];

                // Insert payment record
                $payment_method = 'Cash';
                $payment_amount = (float)$unit_price;
                $payment_status = 'PAID';
                $description = "Cash payment for document request: $document_type";
                $stmt_payment = $conn->prepare("
                    INSERT INTO payments (request_id, payment_method, amount, payment_status, description, payment_date, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt_payment->bind_param("isdss", $id, $payment_method, $payment_amount, $payment_status, $description);
                if (!$stmt_payment->execute()) {
                    throw new Exception('Failed to insert payment record: ' . $stmt_payment->error);
                }
                $stmt_payment->close();

                // Update request
                $stmt = $conn->prepare("
                    UPDATE requests 
                    SET payment_status = 'paid', payment_method = 'cash', updated_at = NOW() 
                    WHERE id = ? AND payment_status = 'Awaiting Payment'
                ");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Check for To Release status
                    $stmt = $conn->prepare("
                        UPDATE requests 
                        SET status = 'To Release' 
                        WHERE id = ? AND payment_status = 'paid' AND status = 'Ready to Pickup'
                    ");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    logAction($conn, 'Mark Paid', "Request ID: $id", "Payment status changed to paid, method set to cash by {$_SESSION['role']}");

                    // Send email notification
                    $email_result = sendPaymentConfirmation($student_email, $firstname, $lastname, $document_type, $id, 'Cash');
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Payment confirmation sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send payment confirmation to $student_email: $error_message");
                    }

                    // Create in-system notification
                    $message = "Your $document_type (Request ID: $id) payment has been confirmed via Cash.";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, message, link, is_read, created_at) 
                        VALUES (?, ?, ?, 0, NOW())
                    ");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $id", "Payment confirmation notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $id", "Failed to create payment confirmation notification for user $user_id: $error_message");
                    }

                    $_SESSION['message'] = "Request ID $id marked as paid.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to mark request as paid or request is not in Awaiting Payment status.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found, not in Awaiting Payment status, or not a cash payment.'];
            }
            $stmt->close();
            break;

        // For payment_logs.php: Fetch payment details
        case 'get_payment':
            if (!in_array($_SESSION['role'], ['registrar', 'staff', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars, staff, and cashiers can view payment details.'];
                break;
            }
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("
                SELECT p.id, p.request_id, p.payment_method, p.amount, p.payment_status, p.description, p.payment_date,
                       r.document_type, r.status AS request_status, r.requested_date,
                       CONCAT(u.firstname, ' ', u.lastname) AS student_name, u.email, u.number
                FROM payments p
                LEFT JOIN requests r ON p.request_id = r.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE p.id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response = [
                    'status' => 'success',
                    'payment' => [
                        'id' => $row['id'],
                        'request_id' => $row['request_id'],
                        'payment_method' => $row['payment_method'],
                        'amount' => $row['amount'],
                        'payment_status' => $row['payment_status'],
                        'description' => $row['description'],
                        'payment_date' => $row['payment_date']
                    ],
                    'request' => [
                        'document_type' => $row['document_type'],
                        'status' => $row['request_status'],
                        'requested_date' => $row['requested_date'],
                        'student_name' => $row['student_name'],
                        'email' => $row['email'],
                        'number' => $row['number']
                    ]
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'Payment not found.'];
            }
            $stmt->close();
            break;

        // For payment_logs.php: Fetch request details for Awaiting Payment
        case 'get_request':
            if (!in_array($_SESSION['role'], ['registrar', 'staff', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars, staff, and cashiers can view request details.'];
                break;
            }
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("
                SELECT 
                    r.id, 
                    r.document_type, 
                    r.unit_price, 
                    r.payment_status, 
                    r.status AS request_status, 
                    r.requested_date,
                    CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                    u.email, 
                    u.number
                FROM requests r
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ? AND r.payment_status = 'Awaiting Payment'
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $response = [
                    'status' => 'success',
                    'request' => [
                        'id' => $row['id'],
                        'document_type' => $row['document_type'],
                        'unit_price' => $row['unit_price'],
                        'payment_status' => $row['payment_status'],
                        'status' => $row['request_status'],
                        'requested_date' => $row['requested_date'],
                        'student_name' => $row['student_name'],
                        'email' => $row['email'],
                        'number' => $row['number']
                    ]
                ];
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in Awaiting Payment status.'];
            }
            $stmt->close();
            break;

        // For payment_logs.php: Archive a single payment
        case 'archive':
            if (!in_array($_SESSION['role'], ['registrar', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars and cashiers can archive payments.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE payments SET archived = 1, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Archive Payment', "Payment ID: $id", "Payment archived by {$_SESSION['role']}");
                $_SESSION['message'] = "Payment ID $id archived successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to archive payment or payment not found.'];
            }
            $stmt->close();
            break;

        // For payment_logs.php: Bulk archive payments
        case 'bulk_archive':
            if (!in_array($_SESSION['role'], ['registrar', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars and cashiers can archive payments.'];
                break;
            }
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid payments selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE payments SET archived = 1, updated_at = NOW() WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $payment_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Archive Payments', "Count: $affected", "Payment IDs: $payment_ids by {$_SESSION['role']}");
                    $_SESSION['message'] = "$affected payment(s) archived successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'No payments were archived.'];
                }
            } else {
                throw new Exception("Failed to bulk archive payments: " . $stmt->error);
            }
            $stmt->close();
            break;

        // For payment_logs.php: Retrieve (unarchive) a payment
        case 'retrieve':
            if (!in_array($_SESSION['role'], ['registrar', 'cashier'])) {
                $response = ['status' => 'error', 'message' => 'Only registrars and cashiers can retrieve payments.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE payments SET archived = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Retrieve Payment', "Payment ID: $id", "Payment retrieved from archive by {$_SESSION['role']}");
                $_SESSION['message'] = "Payment ID $id retrieved successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to retrieve payment or payment not found.'];
            }
            $stmt->close();
            break;

        // For payment_logs.php: Delete a single payment
        case 'delete':
            if ($_SESSION['role'] !== 'registrar') {
                $response = ['status' => 'error', 'message' => 'Only registrars can delete payments.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Delete Payment', "Payment ID: $id", "Payment deleted by registrar");
                $_SESSION['message'] = "Payment ID $id deleted successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to delete payment or payment not found.'];
            }
            $stmt->close();
            break;

        // For payment_logs.php: Bulk delete payments
        case 'bulk_delete':
            if ($_SESSION['role'] !== 'registrar') {
                $response = ['status' => 'error', 'message' => 'Only registrars can delete payments.'];
                break;
            }
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid payments selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("DELETE FROM payments WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $payment_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Delete Payments', "Count: $affected", "Payment IDs: $payment_ids by registrar");
                    $_SESSION['message'] = "$affected payment(s) deleted successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'No payments were deleted.'];
                }
            } else {
                throw new Exception("Failed to bulk delete payments: " . $stmt->error);
            }
            $stmt->close();
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action.'];
            break;
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
    logAction($conn, 'Error', "Action: $action", "Exception: " . $e->getMessage());
}

// Close database connection
if ($conn) {
    $conn->close();
}

// Clean output buffer and send JSON response
ob_end_clean();
echo json_encode($response);
exit();
?>