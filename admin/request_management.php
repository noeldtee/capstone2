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
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['admin', 'registrar', 'cashier'])) {
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
            $stmt = $conn->prepare("
                SELECT 
                    r.id, 
                    r.document_type, 
                    CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                    r.unit_price, 
                    r.status, 
                    r.requested_date, 
                    r.file_path, 
                    r.remarks, 
                    r.rejection_reason, 
                    r.payment_status,
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

        case 'reject':
            $id = (int)$_POST['id'];
            $rejection_reason = trim($_POST['rejection_reason']);
            if (empty($rejection_reason)) {
                $response = ['status' => 'error', 'message' => 'Rejection reason is required.'];
                break;
            }
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
                $stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param("si", $rejection_reason, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    logAction($conn, 'Reject Request', "Request ID: $id", "Status changed to Rejected, Reason: $rejection_reason");

                    // Send rejection email
                    $email_result = sendRejectionNotification($student_email, $firstname, $lastname, $document_type, $id, $rejection_reason);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Rejection notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send rejection notification to $student_email: $error_message");
                    }

                    // Insert dashboard notification
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
            $id = (int)$_POST['id'];
            // Generate pickup token
            $pickup_token = bin2hex(random_bytes(16));
            // Fetch student details for notification
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

                // Update the request status and set pickup token
                $stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', pickup_token = ?, updated_at = NOW() WHERE id = ? AND status = 'In Process'");
                $stmt->bind_param("si", $pickup_token, $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Generate QR code
                    $qr_dir = '../Uploads/qrcodes/';
                    if (!file_exists($qr_dir)) {
                        mkdir($qr_dir, 0777, true);
                    }
                    $qr_file = $qr_dir . "request_$id.png";
                    $qr_url = "http://yourdomain.com/capstone-admin/verify_qr.php?token=$pickup_token"; // Update with your domain
                    QRcode::png($qr_url, $qr_file, QR_ECLEVEL_L, 10);

                    logAction($conn, 'Mark Ready', "Request ID: $id", "Status changed to Ready to Pickup");

                    // Send pickup email with QR code
                    $email_result = sendPickupNotification($student_email, $firstname, $lastname, $document_type, $id, $qr_file);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $id", "Pickup notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $id", "Failed to send pickup notification to $student_email: $error_message");
                    }

                    // Insert dashboard notification
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

        case 'mark_completed':
            $id = (int)$_POST['id'];
            // Fetch request details
            $stmt = $conn->prepare("SELECT r.document_type, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id = ? AND r.status = 'Ready to Pickup'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $document_type = $row['document_type'];
                $user_id = $row['user_id'];

                // Update the request status and clear pickup token
                $stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id = ? AND status = 'Ready to Pickup'");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    // Delete QR code file
                    $qr_file = "../Uploads/qrcodes/request_$id.png";
                    if (file_exists($qr_file)) {
                        unlink($qr_file);
                    }

                    logAction($conn, 'Mark Completed', "Request ID: $id", "Status changed to Completed");

                    // Insert dashboard notification
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
                    $response = ['status' => 'error', 'message' => 'Failed to mark request as Completed or request is not Ready to Pickup.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found or not in Ready to Pickup status.'];
            }
            $stmt->close();
            break;

        case 'bulk_approve':
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No requests selected.'];
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
            $approved_count = 0;
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $request_id = $row['id'];
                $document_type = $row['document_type'];
                $student_email = $row['email'];
                $firstname = $row['firstname'];
                $lastname = $row['lastname'];
                $user_id = $row['user_id'];

                // Update each request
                $update_stmt = $conn->prepare("UPDATE requests SET status = 'In Process', updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $update_stmt->bind_param("i", $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $approved_count++;
                    logAction($conn, 'Bulk Approve Request', "Request ID: $request_id", "Status changed to In Process");

                    // Send approval email
                    $email_result = sendApprovalNotification($student_email, $firstname, $lastname, $document_type, $request_id);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Approval notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send approval notification to $student_email: $error_message");
                    }

                    // Prepare dashboard notification
                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) has been approved and is now In Process.",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            // Insert all notifications
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

                // Generate pickup token
                $pickup_token = bin2hex(random_bytes(16));

                // Update each request
                $update_stmt = $conn->prepare("UPDATE requests SET status = 'Ready to Pickup', pickup_token = ?, updated_at = NOW() WHERE id = ? AND status = 'In Process'");
                $update_stmt->bind_param("si", $pickup_token, $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $marked_count++;
                    // Generate QR code
                    $qr_dir = '../Uploads/qrcodes/';
                    if (!file_exists($qr_dir)) {
                        mkdir($qr_dir, 0777, true);
                    }
                    $qr_file = $qr_dir . "request_$request_id.png";
                    $qr_url = "http://yourdomain.com/capstone-admin/verify_qr.php?token=$pickup_token"; // Update with your domain
                    QRcode::png($qr_url, $qr_file, QR_ECLEVEL_L, 10);

                    logAction($conn, 'Bulk Mark Ready', "Request ID: $request_id", "Status changed to Ready to Pickup");

                    // Send pickup email with QR code
                    $email_result = sendPickupNotification($student_email, $firstname, $lastname, $document_type, $request_id, $qr_file);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Pickup notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send pickup notification to $student_email: $error_message");
                    }

                    // Prepare dashboard notification
                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) is Ready to Pickup. Please check your email for the QR code.",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            // Insert all notifications
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
            $ids = isset($_POST['ids']) ? array_map('intval', explode(',', $_POST['ids'])) : [];
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT r.id, r.document_type, u.id AS user_id 
                                    FROM requests r 
                                    JOIN users u ON r.user_id = u.id 
                                    WHERE r.id IN ($placeholders) AND r.status = 'Ready to Pickup'");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $completed_count = 0;
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $request_id = $row['id'];
                $document_type = $row['document_type'];
                $user_id = $row['user_id'];

                // Update each request
                $update_stmt = $conn->prepare("UPDATE requests SET status = 'Completed', pickup_token = NULL, updated_at = NOW() WHERE id = ? AND status = 'Ready to Pickup'");
                $update_stmt->bind_param("i", $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $completed_count++;
                    // Delete QR code file
                    $qr_file = "../Uploads/qrcodes/request_$request_id.png";
                    if (file_exists($qr_file)) {
                        unlink($qr_file);
                    }

                    logAction($conn, 'Bulk Mark Completed', "Request ID: $request_id", "Status changed to Completed");

                    // Prepare dashboard notification
                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) has been marked as Completed.",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            // Insert all notifications
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
                $response = ['status' => 'error', 'message' => 'No requests were marked as Completed. Ensure the selected requests are Ready to Pickup.'];
            }
            break;

        case 'bulk_reject':
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

                // Update each request
                $update_stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'Pending'");
                $update_stmt->bind_param("si", $rejection_reason, $request_id);
                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $rejected_count++;
                    logAction($conn, 'Bulk Reject Request', "Request ID: $request_id", "Status changed to Rejected, Reason: $rejection_reason");

                    // Send rejection email
                    $email_result = sendRejectionNotification($student_email, $firstname, $lastname, $document_type, $request_id, $rejection_reason);
                    if ($email_result['success']) {
                        logAction($conn, 'Email Sent', "Request ID: $request_id", "Rejection notification sent to $student_email");
                    } else {
                        $error_message = $email_result['error'] ?? 'Unknown error';
                        logAction($conn, 'Email Failed', "Request ID: $request_id", "Failed to send rejection notification to $student_email: $error_message");
                    }

                    // Prepare dashboard notification
                    $notifications[] = [
                        'user_id' => $user_id,
                        'message' => "Your $document_type (Request ID: $request_id) has been rejected. Reason: $rejection_reason",
                        'link' => "../users/history.php?id=$request_id"
                    ];
                }
                $update_stmt->close();
            }
            $stmt->close();

            // Insert all notifications
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
}

// Output the response
echo json_encode($response);

// Close database connection
if (isset($conn)) {
    $conn->close();
}

ob_end_flush();
?>