<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ob_start();

// Include necessary files
require '../config/function.php';
require '../config/send_email.php';
// Include QR code library
require '../libs/phpqrcode/qrlib.php';

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
        case 'approve':
            $id = (int)$_POST['id'];
            // Fetch student details for notification
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

                // Update the request status
                $stmt = $conn->prepare("UPDATE requests SET status = 'In Process', updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAction($conn, 'Approve Request', "Request ID: $id", "Status changed to In Process");

                    // Send approval email
                    $email_result = sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $id);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Approval notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send approval notification to $student_email: $error_message");
                    }

                    // Insert dashboard notification
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

        case 'bulk_approve':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid requests selected.'];
                break;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            // Fetch student details for notifications
            $stmt = $conn->prepare("SELECT r.id, r.document_type, u.email, u.firstname, u.lastname, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id IN ($placeholders) AND r.status = 'Pending'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $requests_to_notify = [];
            while ($row = $result->fetch_assoc()) {
                $requests_to_notify[$row['id']] = $row;
            }

            // Update the requests
            $stmt = $conn->prepare("UPDATE requests SET status = 'In Process', updated_at = NOW() WHERE id IN ($placeholders) AND status = 'Pending'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Approve Requests', "Count: $affected", "Request IDs: $request_ids");

                    // Send notifications for each approved request
                    foreach ($requests_to_notify as $request_id => $request) {
                        $document_type = $request['document_type'];
                        $student_email = $request['email'];
                        $firstname = $request['firstname'];
                        $lastname = $request['lastname'];
                        $user_id = $request['user_id'];

                        // Send approval email
                        $email_result = sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $request_id);
                        if ($email_result['success']) {
                            logAction($conn, 'Email Sent', "Request ID: $request_id", "Approval notification sent to $student_email");
                        } else {
                            $error_message = $email_result['error'] ?? 'Unknown error';
                            logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send approval notification to $student_email: $error_message");
                        }

                        // Insert dashboard notification
                        $message = "Your $document_type (Request ID: $request_id) has been approved and is now In Process.";
                        $link = "../users/history.php?id=$request_id";
                        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                        $stmt->bind_param("iss", $user_id, $message, $link);
                        if ($stmt->execute()) {
                            logAction($conn, 'Notification Created', "Request ID: $request_id", "Approval notification sent to user $user_id");
                        } else {
                            $error_message = $conn->error;
                            logAction($conn, 'Notification Failed', "Request ID: $request_id", "Failed to create approval notification for user $user_id: $error_message");
                        }
                    }

                    $_SESSION['message'] = "$affected request(s) approved successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'No Pending requests were approved.'];
                }
            } else {
                throw new Exception("Failed to bulk approve requests: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'reject':
            $id = (int)$_POST['id'];
            $reason = validate($_POST['rejection_reason']);
            if (empty($reason)) {
                $response = ['status' => 'error', 'message' => 'Rejection reason is required.'];
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
                $response = ['status' => 'error', 'message' => 'Failed to reject request or request is not in Pending status.'];
            }
            $stmt->close();
            break;

        case 'bulk_reject':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            $reason = validate($_POST['rejection_reason']);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid requests selected.'];
                break;
            }
            if (empty($reason)) {
                $response = ['status' => 'error', 'message' => 'Rejection reason is required.'];
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
                    $response = ['status' => 'error', 'message' => 'No Pending requests were rejected.'];
                }
            } else {
                throw new Exception("Failed to bulk reject requests: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'mark_ready':
            $id = (int)$_POST['id'];
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

                // Generate a unique pickup token
                $secret_key = 'your-secret-key-here'; // Replace with a secure key
                $pickup_token = hash('sha256', $id . $user_id . time() . $secret_key);

                // Generate QR code
                $qr_url = "http://localhost/capstone-admin/verify_qr.php?token=" . $pickup_token; // Update to your domain
                $qr_file = "../uploads/qrcodes/request_$id.png";
                QRcode::png($qr_url, $qr_file, QR_ECLEVEL_L, 4);

                // Update the request status and store the pickup token
                $stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', pickup_token = ?, updated_at = NOW() WHERE id = ? AND status = 'In Process'");
                $stmt->bind_param("si", $pickup_token, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAction($conn, 'Mark Ready Request', "Request ID: $id", "Status changed to Ready to Pickup, Token: $pickup_token");

                    // Send email notification with QR code
                    $email_result = sendPickupNotification($student_email, $firstname, $lastname, $document_type, $id, $qr_file);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Pickup notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send pickup notification to $student_email: $error_message");
                    }

                    // Insert dashboard notification
                    $message = "Your $document_type (Request ID: $id) is Ready to Pickup!";
                    $link = "../users/history.php?id=$id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $id", "Notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $id", "Failed to create notification for user $user_id: $error_message");
                    }

                    $_SESSION['message'] = "Request ID $id marked as Ready to Pickup.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to mark request as ready or request is not In Process.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in In Process status.'];
            }
            $stmt->close();
            break;

        case 'bulk_mark_ready':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid requests selected.'];
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
            $requests_to_notify = [];
            while ($row = $result->fetch_assoc()) {
                $requests_to_notify[$row['id']] = $row;
            }

            $affected = 0;
            $secret_key = 'your-secret-key-here'; // Replace with a secure key
            foreach ($requests_to_notify as $request_id => $request) {
                // Generate a unique pickup token for each request
                $pickup_token = hash('sha256', $request_id . $request['user_id'] . time() . $secret_key);

                // Generate QR code
                $qr_url = "http://localhost/capstone-admin/verify_qr.php?token=" . $pickup_token; // Update to your domain
                $qr_file = "../uploads/qrcodes/request_$request_id.png";
                QRcode::png($qr_url, $qr_file, QR_ECLEVEL_L, 4);

                // Update the request with the pickup token
                $stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', pickup_token = ?, updated_at = NOW() WHERE id = ? AND status = 'In Process'");
                $stmt->bind_param("si", $pickup_token, $request_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $affected++;
                    logAction($conn, 'Mark Ready Request', "Request ID: $request_id", "Status changed to Ready to Pickup, Token: $pickup_token");
                }
            }

            if ($affected > 0) {
                $request_ids = implode(', ', $ids);
                logAction($conn, 'Bulk Mark Ready Request', "Count: $affected", "Request IDs: $request_ids");

                foreach ($requests_to_notify as $request_id => $request) {
                    $document_type = $request['document_type'];
                    $student_email = $request['email'];
                    $firstname = $request['firstname'];
                    $lastname = $request['lastname'];
                    $user_id = $request['user_id'];

                    // Send email notification with QR code
                    $qr_file = "../uploads/qrcodes/request_$request_id.png";
                    $email_result = sendPickupNotification($student_email, $firstname, $lastname, $document_type, $request_id, $qr_file);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Pickup notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send pickup notification to $student_email: $error_message");
                    }

                    // Insert dashboard notification
                    $message = "Your $document_type (Request ID: $request_id) is Ready to Pickup!";
                    $link = "../users/history.php?id=$request_id";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        logAction($conn, 'Notification Created', "Request ID: $request_id", "Notification sent to user $user_id");
                    } else {
                        $error_message = $conn->error;
                        logAction($conn, 'Notification Failed', "Request ID: $request_id", "Failed to create notification for user $user_id: $error_message");
                    }
                }

                $_SESSION['message'] = "$affected request(s) marked as Ready to Pickup.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'No In Process requests were marked as ready.'];
            }
            $stmt->close();
            break;

        case 'mark_completed':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id = ? AND status = 'Ready to Pickup'");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Optionally delete the QR code file
                $qr_file = "../uploads/qrcodes/request_$id.png";
                if (file_exists($qr_file)) {
                    unlink($qr_file);
                }
                logAction($conn, 'Mark Completed Request', "Request ID: $id", "Status changed to Completed");
                $_SESSION['message'] = "Request ID $id marked as Completed.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to mark request as completed or request is not Ready to Pickup.'];
            }
            $stmt->close();
            break;

        case 'bulk_mark_completed':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id IN ($placeholders) AND status = 'Ready to Pickup'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    // Delete QR code files
                    foreach ($ids as $id) {
                        $qr_file = "../uploads/qrcodes/request_$id.png";
                        if (file_exists($qr_file)) {
                            unlink($qr_file);
                        }
                    }
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Mark Completed Requests', "Count: $affected", "Request IDs: $request_ids");
                    $_SESSION['message'] = "$affected request(s) marked as Completed.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'No Ready to Pickup requests were marked as completed.'];
                }
            } else {
                throw new Exception("Failed to bulk mark requests as completed: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'get':
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT r.id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                           r.unit_price, r.status, r.requested_date, r.file_path, r.remarks 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ?");
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

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action.'];
            break;
    }
} catch (Exception $e) {
    error_log("Error in request_management.php: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()];
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = 'danger';
}

// Clean output buffer and send JSON response
ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>