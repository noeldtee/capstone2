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
require '../libs/phpqrcode/qrlib.php';

// Check if user is logged in
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['registrar', 'staff', 'cashier'])) {
    $response = ['status' => 'error', 'message' => 'Please log in as a registrar, staff, or cashier to perform this action.'];
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
    // Debug: Test a simple query
    $result = $conn->query("SELECT 1");
    if (!$result) {
        throw new Exception('Database query failed: ' . $conn->error);
    }

    switch ($action) {
        case 'get':
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
                    r.file_path, 
                    r.remarks, 
                    r.rejection_reason,
                    u.number, 
                    u.email, 
                    u.year_level, 
                    c.name AS course_name, 
                    s.section AS section_name, 
                    sy.year AS school_year
                FROM requests r 
                JOIN users u ON r.user_id = u.id 
                LEFT JOIN courses c ON r.course_id = c.id 
                LEFT JOIN sections s ON r.section_id = s.id 
                LEFT JOIN school_years sy ON r.year_id = sy.id 
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

        case 'approve':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $id = (int)$_POST['id'];
            // Debug: Log request details
            $debug_stmt = $conn->prepare("SELECT status, payment_status, payment_method FROM requests WHERE id = ?");
            $debug_stmt->bind_param("i", $id);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            if ($debug_result->num_rows > 0) {
                $debug_row = $debug_result->fetch_assoc();
                error_log("Approve Request ID: $id, Status: {$debug_row['status']}, Payment Status: {$debug_row['payment_status']}, Payment Method: {$debug_row['payment_method']}");
            } else {
                error_log("Approve Request ID: $id not found in requests table.");
            }
            $debug_stmt->close();
        
            $stmt = $conn->prepare("SELECT r.document_type, r.payment_status, r.payment_method, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ? AND r.status = 'Pending'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $current_payment_status = $row['payment_status'];
                $payment_method = $row['payment_method'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];
        
                // Determine the new payment status
                $new_payment_status = $current_payment_status;
                if (strtolower($payment_method) === 'cash' && strtolower($current_payment_status) !== 'paid') {
                    $new_payment_status = 'Awaiting Payment';
                }
        
                $stmt = $conn->prepare("UPDATE requests SET status = 'In Process', payment_status = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param("si", $new_payment_status, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAction($conn, 'Approve Request', "Request ID: $id", "Status changed to In Process, Payment Status set to $new_payment_status");
        
                    $email_result = sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $id);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Approval notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send approval notification to $student_email: $error_message");
                    }
        
                    $message = "Your $document_type (Request ID: $id) has been approved and is now In Process.";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $id", "Approval notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $id", "Failed to create approval notification for user $user_id: $error_message");
                    }
        
                    $_SESSION['message'] = "Request ID $id approved successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to approve request or request is not in Pending status.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in Pending status.'];
            }
            $stmt->close();
            break;

        case 'reject':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $id = (int)$_POST['id'];
            $rejection_reason = trim($_POST['rejection_reason']);
            if (empty($rejection_reason)) {
                $response = ['status' => 'error', 'message' => 'Rejection reason is required.'];
                break;
            }
            $stmt = $conn->prepare("SELECT r.document_type, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ? AND r.status = 'Pending'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];

                $stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param("si", $rejection_reason, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAction($conn, 'Reject Request', "Request ID: $id", "Status changed to Rejected, Reason: $rejection_reason");

                    $email_result = sendRejectionNotification($student_email, $firstname, $lastname, $document_type, $id, $rejection_reason);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Rejection notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send rejection notification to $student_email: $error_message");
                    }

                    $message = "Your $document_type (Request ID: $id) has been rejected. Reason: $rejection_reason";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $id", "Rejection notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $id", "Failed to create rejection notification for user $user_id: $error_message");
                    }

                    $_SESSION['message'] = "Request ID $id rejected successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to reject request or request is not in Pending status.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in Pending status.'];
            }
            $stmt->close();
            break;

        case 'mark_ready':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $id = (int)$_POST['id'];
            $pickup_token = bin2hex(random_bytes(16));
            $stmt = $conn->prepare("SELECT r.document_type, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ? AND r.status = 'In Process'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];

                $stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', pickup_token = ?, updated_at = NOW() WHERE id = ? AND status = 'In Process'");
                $stmt->bind_param("si", $pickup_token, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $qr_dir = '../Uploads/qrcodes/';
                    if (!file_exists($qr_dir)) {
                        if (!mkdir($qr_dir, 0755, true)) {
                            throw new Exception('Failed to create QR code directory.');
                        }
                    }
                    $qr_file = $qr_dir . "request_$id.png";
                    $qr_url = "https://yourdomain.com/verify_qr.php?token=$pickup_token"; // Update with your domain
                    try {
                        QRcode::png($qr_url, $qr_file, QR_ECLEVEL_L, 10);
                        if (!file_exists($qr_file)) {
                            throw new Exception('Failed to generate QR code.');
                        }
                    } catch (Exception $e) {
                        logAction($conn, 'QR Code Failed', "Request ID: $id", "Failed to generate QR code: " . $e->getMessage());
                        throw $e;
                    }

                    // Check for To Release status
                    $stmt = $conn->prepare("UPDATE requests SET status = 'To Release' WHERE id = ? AND payment_status = 'paid' AND status = 'Ready to Pickup'");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    logAction($conn, 'Mark Ready', "Request ID: $id", "Status changed to Ready to Pickup");

                    $email_result = sendPickupNotification($student_email, $firstname, $lastname, $document_type, $id, $qr_file);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Pickup notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send pickup notification to $student_email: $error_message");
                    }

                    $message = "Your $document_type (Request ID: $id) is Ready to Pickup. Please check your email for the QR code.";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $id", "Pickup notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $id", "Failed to create pickup notification for user $user_id: $error_message");
                    }

                    $_SESSION['message'] = "Request ID $id marked as Ready to Pickup.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to mark request as Ready to Pickup or request is not In Process.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in In Process status.'];
            }
            $stmt->close();
            break;

        case 'mark_paid':
            if ($_SESSION['role'] !== 'cashier') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT r.document_type, r.unit_price, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ? AND r.payment_status = 'Awaiting Payment'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $unit_price = $row['unit_price'];
                $user_id = $row['user_id'];

                // Insert payment record
                $payment_method = 'Cash';
                $payment_amount = (float)$unit_price;
                $payment_status = 'PAID';
                $description = "Cash payment for document request: $document_type";
                $stmt_payment = $conn->prepare("INSERT INTO payments (request_id, payment_method, amount, payment_status, description, payment_date, created_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt_payment->bind_param("isdss", $id, $payment_method, $payment_amount, $payment_status, $description);
                if (!$stmt_payment->execute()) {
                    throw new Exception('Failed to insert payment record: ' . $stmt_payment->error);
                }
                $stmt_payment->close();

                // Update request
                $stmt = $conn->prepare("UPDATE requests SET payment_status = 'paid', payment_method = 'cash', updated_at = NOW() WHERE id = ? AND payment_status = 'Awaiting Payment'");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Check for To Release status
                    $stmt = $conn->prepare("UPDATE requests SET status = 'To Release' WHERE id = ? AND payment_status = 'paid' AND status = 'Ready to Pickup'");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();

                    logAction($conn, 'Mark Paid', "Request ID: $id", "Payment status changed to paid, method set to cash");

                    $message = "Your $document_type (Request ID: $id) payment has been confirmed via Cash.";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
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
                $response = ['status' => 'error', 'message' => 'Request not found or not in Awaiting Payment status.'];
            }
            $stmt->close();
            break;

        case 'mark_completed':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT r.document_type, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ? AND r.status = 'To Release'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $user_id = $row['user_id'];

                $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id = ? AND status = 'To Release'");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $qr_file = "../Uploads/qrcodes/request_$id.png";
                    if (file_exists($qr_file)) {
                        unlink($qr_file);
                    }

                    logAction($conn, 'Mark Completed', "Request ID: $id", "Status changed to Completed");

                    $message = "Your $document_type (Request ID: $id) has been marked as Completed.";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $id", "Completion notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $id", "Failed to create completion notification for user $user_id: $error_message");
                    }

                    $_SESSION['message'] = "Request ID $id marked as Completed.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to mark request as Completed or request is not in To Release status.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in To Release status.'];
            }
            $stmt->close();
            break;

        case 'bulk_approve':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT r.id, r.document_type, r.payment_status, r.payment_method, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id IN ($placeholders) AND r.status = 'Pending'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $approved_count = 0;
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $request_id = $row['id'];
                $document_type = $row['document_type'];
                $current_payment_status = $row['payment_status'];
                $payment_method = $row['payment_method'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];

                // Determine the new payment status
                $new_payment_status = $current_payment_status;
                if (strtolower($payment_method) === 'cash' && strtolower($current_payment_status) !== 'paid') {
                    $new_payment_status = 'Awaiting Payment';
                }

                $update_stmt = $conn->prepare("UPDATE requests SET status = 'In Process', payment_status = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $update_stmt->bind_param("si", $new_payment_status, $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $approved_count++;
                    logAction($conn, 'Bulk Approve Request', "Request ID: $request_id", "Status changed to In Process, Payment Status set to $new_payment_status");

                    $email_result = sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $request_id);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Approval notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send approval notification to $student_email: $error_message");
                    }

                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) has been approved and is now In Process.",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            foreach ($notifications as $notif) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->bind_param("iss", $notif['user_id'], $notif['message'], $notif['link']);
                if ($stmt->execute()) {
                    logAction($conn, 'Notification Created', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Approval notification sent to user {$notif['user_id']}");
                } else {
                    $error_message = $conn->error;
                    logAction($conn, 'Notification Failed', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Failed to create approval notification for user {$notif['user_id']}: $error_message");
                }
                $stmt->close();
            }

            if ($approved_count > 0) {
                $_SESSION['message'] = "$approved_count request(s) approved successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No requests were approved. Ensure the selected requests are in Pending status.'];
            }
            break;

        case 'bulk_mark_ready':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT r.id, r.document_type, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id IN ($placeholders) AND r.status = 'In Process'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $marked_count = 0;
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $request_id = $row['id'];
                $document_type = $row['document_type'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];

                $pickup_token = bin2hex(random_bytes(16));

                $update_stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', pickup_token = ?, updated_at = NOW() WHERE id = ? AND status = 'In Process'");
                $update_stmt->bind_param("si", $pickup_token, $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $marked_count++;
                    $qr_dir = '../Uploads/qrcodes/';
                    if (!file_exists($qr_dir)) {
                        if (!mkdir($qr_dir, 0755, true)) {
                            throw new Exception('Failed to create QR code directory.');
                        }
                    }
                    $qr_file = $qr_dir . "request_$request_id.png";
                    $qr_url = "https://yourdomain.com/verify_qr.php?token=$pickup_token"; // Update with your domain
                    try {
                        QRcode::png($qr_url, $qr_file, QR_ECLEVEL_L, 10);
                        if (!file_exists($qr_file)) {
                            throw new Exception('Failed to generate QR code.');
                        }
                    } catch (Exception $e) {
                        logAction($conn, 'QR Code Failed', "Request ID: $request_id", "Failed to generate QR code: " . $e->getMessage());
                        throw $e;
                    }

                    // Check for To Release status
                    $update_stmt = $conn->prepare("UPDATE requests SET status = 'To Release' WHERE id = ? AND payment_status = 'paid' AND status = 'Ready to Pickup'");
                    $update_stmt->bind_param("i", $request_id);
                    $update_stmt->execute();

                    logAction($conn, 'Bulk Mark Ready', "Request ID: $request_id", "Status changed to Ready to Pickup");

                    $email_result = sendPickupNotification($student_email, $firstname, $lastname, $document_type, $request_id, $qr_file);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Pickup notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send pickup notification to $student_email: $error_message");
                    }

                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) is Ready to Pickup. Please check your email for the QR code.",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            foreach ($notifications as $notif) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->bind_param("iss", $notif['user_id'], $notif['message'], $notif['link']);
                if ($stmt->execute()) {
                    logAction($conn, 'Notification Created', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Pickup notification sent to user {$notif['user_id']}");
                } else {
                    $error_message = $conn->error;
                    logAction($conn, 'Notification Failed', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Failed to create pickup notification for user {$notif['user_id']}: $error_message");
                }
                $stmt->close();
            }

            if ($marked_count > 0) {
                $_SESSION['message'] = "$marked_count request(s) marked as Ready to Pickup.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No requests were marked as Ready to Pickup. Ensure the selected requests are In Process.'];
            }
            break;

        case 'bulk_mark_completed':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT r.id, r.document_type, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id IN ($placeholders) AND r.status = 'To Release'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $completed_count = 0;
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $request_id = $row['id'];
                $document_type = $row['document_type'];
                $user_id = $row['user_id'];

                $update_stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id = ? AND status = 'To Release'");
                $update_stmt->bind_param("i", $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $completed_count++;
                    $qr_file = "../Uploads/qrcodes/request_$request_id.png";
                    if (file_exists($qr_file)) {
                        unlink($qr_file);
                    }

                    logAction($conn, 'Bulk Mark Completed', "Request ID: $request_id", "Status changed to Completed");

                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) has been marked as Completed.",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            foreach ($notifications as $notif) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->bind_param("iss", $notif['user_id'], $notif['message'], $notif['link']);
                if ($stmt->execute()) {
                    logAction($conn, 'Notification Created', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Completion notification sent to user {$notif['user_id']}");
                } else {
                    $error_message = $conn->error;
                    logAction($conn, 'Notification Failed', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Failed to create completion notification for user {$notif['user_id']}: $error_message");
                }
                $stmt->close();
            }

            if ($completed_count > 0) {
                $_SESSION['message'] = "$completed_count request(s) marked as Completed.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No requests were marked as Completed. Ensure the selected requests are in To Release status.'];
            }
            break;

        case 'bulk_reject':
            if ($_SESSION['role'] !== 'registrar' && $_SESSION['role'] !== 'staff') {
                $response = ['status' => 'error', 'message' => 'Unauthorized action.'];
                break;
            }
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            $rejection_reason = trim($_POST['rejection_reason']);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No requests selected.'];
                break;
            }
            if (empty($rejection_reason)) {
                $response = ['status' => 'error', 'message' => 'Rejection reason is required.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT r.id, r.document_type, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id IN ($placeholders) AND r.status = 'Pending'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $rejected_count = 0;
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $request_id = $row['id'];
                $document_type = $row['document_type'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];

                $update_stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $update_stmt->bind_param("si", $rejection_reason, $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $rejected_count++;
                    logAction($conn, 'Bulk Reject Request', "Request ID: $request_id", "Status changed to Rejected, Reason: $rejection_reason");

                    $email_result = sendRejectionNotification($student_email, $firstname, $lastname, $document_type, $request_id, $rejection_reason);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Rejection notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send rejection notification to $student_email: $error_message");
                    }

                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) has been rejected. Reason: $rejection_reason",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            foreach ($notifications as $notif) {
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->bind_param("iss", $notif['user_id'], $notif['message'], $notif['link']);
                if ($stmt->execute()) {
                    logAction($conn, 'Notification Created', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Rejection notification sent to user {$notif['user_id']}");
                } else {
                    $error_message = $conn->error;
                    logAction($conn, 'Notification Failed', "Request ID: " . basename(parse_url($notif['link'], PHP_URL_QUERY), '=') . "", "Failed to create rejection notification for user {$notif['user_id']}: $error_message");
                }
                $stmt->close();
            }

            if ($rejected_count > 0) {
                $_SESSION['message'] = "$rejected_count request(s) rejected successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No requests were rejected. Ensure the selected requests are in Pending status.'];
            }
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action specified.'];
            break;
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
    error_log("Error in request_management.php: " . $e->getMessage());
}

// Clean output buffer and send response
ob_end_clean();
echo json_encode($response);

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>