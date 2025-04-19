<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary configurations and functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/function.php';

// Ensure user is authenticated
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['student', 'alumni'])) {
    redirect('../index.php', 'Please log in as a student or alumni to access this page.', 'warning');
    exit();
}

// PayMongo API key
$paymongo_secret_key = 'sk_test_qCKzU9gWR64WpE2ftVFHPVgs';

// Get user ID
$user_id = $_SESSION['user_id'];

// Get documents to request from the URL (passed as JSON)
$documents_json = isset($_GET['request_ids']) ? urldecode($_GET['request_ids']) : '';
$documents_to_request = json_decode($documents_json, true);
$remarks = $_SESSION['remarks'] ?? ''; // Retrieve remarks from session

if (empty($documents_to_request) || !is_array($documents_to_request)) {
    $_SESSION['message'] = 'Invalid request data.';
    $_SESSION['message_type'] = 'danger';
    header('Location: request_document.php');
    exit;
}

// For testing: Assume payment is successful and set payment_status to 'paid'
$payment_successful = true; // Set to true for testing
$checkout_session_id = $_SESSION['checkout_session_id'] ?? 'test_checkout_session';

// If payment is required, verify with PayMongo (disabled for testing)
// if ($payment_successful) {
//     $curl = curl_init();
//     curl_setopt_array($curl, [
//         CURLOPT_URL => "https://api.paymongo.com/v1/checkout_sessions/" . $checkout_session_id,
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_HTTPHEADER => [
//             "Accept: application/json",
//             "Authorization: Basic " . base64_encode($paymongo_secret_key . ":")
//         ],
//     ]);
//     $response = curl_exec($curl);
//     $err = curl_error($curl);
//     curl_close($curl);

//     if ($err) {
//         error_log("PayMongo API poll error: $err");
//         $payment_successful = false;
//     } else {
//         $session_data = json_decode($response, true);
//         error_log("PayMongo API response: " . json_encode($session_data));
//         $payment_status = $session_data['data']['attributes']['payment_status'] ?? 'unknown';
//         $session_status = $session_data['data']['attributes']['status'] ?? 'unknown';
//         $payment_successful = ($payment_status === 'paid' || $session_status === 'paid');
//     }
// }

if (!$payment_successful) {
    // Payment failed, do not insert requests into the database
    $_SESSION['message'] = 'Payment failed or was cancelled. Request not submitted.';
    $_SESSION['message_type'] = 'warning';
    header('Location: request_document.php');
    exit;
}

// Insert requests into the database since payment is successful
$request_ids = [];
$doc_names = array_column($documents_to_request, 'document_type');
$stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, quantity, unit_price, amount, payment_status, status, remarks, file_path, requested_date, created_at) VALUES (?, ?, 1, ?, ?, 'paid', 'Pending', ?, ?, NOW(), NOW())");
foreach ($documents_to_request as $doc) {
    $amount = (int)($doc['unit_price'] * 100);
    $unit_price = (float)$doc['unit_price'];
    $file_path = $doc['file_path'] ?? null;
    $stmt->bind_param("isdiss", $user_id, $doc['document_type'], $unit_price, $amount, $remarks, $file_path);
    if ($stmt->execute()) {
        $request_ids[] = $conn->insert_id;
    } else {
        error_log("Failed to insert request for {$doc['document_type']}: " . $stmt->error);
        $_SESSION['message'] = "Failed to save request for {$doc['document_type']}.";
        $_SESSION['message_type'] = 'danger';
        header('Location: request_document.php');
        exit;
    }
}
$stmt->close();

// Create notifications for student
$message = "Payment for " . implode(' and ', $doc_names) . " was successful.";
$link = "dashboard.php";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
$stmt->bind_param("iss", $user_id, $message, $link);
if ($stmt->execute()) {
    error_log("Notification created for user $user_id: $message");
} else {
    error_log("Failed to create notification for user $user_id: " . $stmt->error);
}
$stmt->close();

// Notify admins
$stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
$stmt->execute();
$admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$student_name = $user['firstname'] . ' ' . $user['lastname'];
$admin_message = "Student $student_name submitted payment for " . implode(' and ', $doc_names) . ".";
$admin_link = "/capstone-admin/admin/request.php?id=" . $request_ids[0];

foreach ($admins as $admin) {
    $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $admin['id'], $admin_message, $admin_link);
    if ($stmt->execute()) {
        error_log("Admin notification created for admin {$admin['id']}: $admin_message");
    } else {
        error_log("Failed to create admin notification for admin {$admin['id']}: " . $stmt->error);
    }
    $stmt->close();
}

// Clear session data
unset($_SESSION['checkout_session_id']);
unset($_SESSION['remarks']);

// Set success message and redirect to request_document.php
$_SESSION['message'] = 'Request successful! Payment completed for ' . implode(' and ', $doc_names) . '.';
$_SESSION['message_type'] = 'success';
header('Location: request_document.php');
exit;