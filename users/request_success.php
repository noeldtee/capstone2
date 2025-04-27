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

// Fetch the current semester from the settings table
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_semester'");
$stmt->execute();
$result = $stmt->get_result();
$current_semester = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
$stmt->close();

if (empty($current_semester)) {
    $_SESSION['message'] = 'Current semester not set. Please contact an administrator.';
    $_SESSION['message_type'] = 'danger';
    header('Location: request_document.php');
    exit;
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Verify payment method
if (!isset($_SESSION['payment_method']) || $_SESSION['payment_method'] !== 'online') {
    $_SESSION['message'] = 'Invalid access. This page is for online payment processing only.';
    $_SESSION['message_type'] = 'danger';
    header('Location: request_document.php');
    exit;
}

// Get documents to request from the URL (passed as JSON)
$documents_json = isset($_GET['request_ids']) ? urldecode($_GET['request_ids']) : '';
$documents_to_request = json_decode($documents_json, true);
$remarks = $_SESSION['remarks'] ?? '';

if (empty($documents_to_request) || !is_array($documents_to_request)) {
    $_SESSION['message'] = 'Invalid request data.';
    $_SESSION['message_type'] = 'danger';
    header('Location: request_document.php');
    exit;
}

// Check if any documents require payment
$requires_payment = false;
foreach ($documents_to_request as $doc) {
    if ((float)$doc['unit_price'] > 0) {
        $requires_payment = true;
        break;
    }
}

// Verify payment with PayMongo if payment is required
$payment_successful = !$requires_payment; // Skip verification if no payment required
$checkout_session_id = $_SESSION['checkout_session_id'] ?? 'test_checkout_session';

if ($requires_payment) {
    // For testing: Assume payment is successful
    $payment_successful = true; // Set to true for testing
    // In production, enable PayMongo verification
    /*
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paymongo.com/v1/checkout_sessions/" . $checkout_session_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Authorization: Basic " . base64_encode($paymongo_secret_key . ":")
        ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("PayMongo API poll error: $err");
        $payment_successful = false;
    } else {
        $session_data = json_decode($response, true);
        error_log("PayMongo API response: " . json_encode($session_data));
        $payment_status = $session_data['data']['attributes']['payment_status'] ?? 'unknown';
        $session_status = $session_data['data']['attributes']['status'] ?? 'unknown';
        $payment_successful = ($payment_status === 'paid' || $session_status === 'paid');
    }
    */
}

if (!$payment_successful) {
    // Payment failed, do not insert requests into the database
    $_SESSION['message'] = 'Payment failed or was cancelled. Request not submitted.';
    $_SESSION['message_type'] = 'warning';
    header('Location: request_document.php');
    exit;
}

// Insert requests into the database
$request_ids = [];
$doc_names = array_column($documents_to_request, 'document_type');
$stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, quantity, unit_price, amount, payment_status, payment_method, status, remarks, file_path, course_id, section_id, year_id, semester, requested_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

// Prepare statement for payments table
$stmt_payment = $conn->prepare("INSERT INTO payments (request_id, payment_method, amount, payment_status, description, payment_date, created_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

foreach ($documents_to_request as $doc) {
    $unit_price = (float)$doc['unit_price'];
    $amount = (int)($unit_price * 100); // In centavos
    $quantity = 1;
    $file_path = $doc['file_path'] ?? null;
    $course_id = $doc['course_id'] ?? null;
    $section_id = $doc['section_id'] ?? null;
    $year_id = $doc['year_id'] ?? null;
    $payment_status = 'paid'; // Online payments or free documents
    $payment_method = $unit_price > 0 ? 'online' : 'N/A';
    $status = 'Pending'; // Define status before bind_param
    // Adjusted type definition string to match the number of parameters
    $stmt->bind_param("isiddssssiisss", $user_id, $doc['document_type'], $quantity, $unit_price, $amount, $payment_status, $payment_method, $status, $remarks, $file_path, $course_id, $section_id, $year_id, $current_semester);
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        $request_ids[] = $request_id;

        // Insert into payments table
        $payment_method_db = $unit_price > 0 ? 'Online' : 'N/A'; // Use 'GCash' or 'Card' from PayMongo response in production
        $payment_amount = $unit_price; // In pesos
        $payment_status_db = 'PAID';
        $description = "Payment for document request: {$doc['document_type']}";
        $stmt_payment->bind_param("isdss", $request_id, $payment_method_db, $payment_amount, $payment_status_db, $description);
        if (!$stmt_payment->execute()) {
            error_log("Failed to insert payment for request ID $request_id: " . $stmt_payment->error);
            $_SESSION['message'] = "Failed to save payment record for {$doc['document_type']}.";
            $_SESSION['message_type'] = 'danger';
            header('Location: request_document.php');
            exit;
        }
    } else {
        error_log("Failed to insert request for {$doc['document_type']}: " . $stmt->error);
        $_SESSION['message'] = "Failed to save request for {$doc['document_type']}.";
        $_SESSION['message_type'] = 'danger';
        header('Location: request_document.php');
        exit;
    }
}
$stmt->close();
$stmt_payment->close();

// Create notifications for student
$paid_docs = array_filter($doc_names, function($name) use ($documents_to_request) {
    foreach ($documents_to_request as $doc) {
        if ($doc['document_type'] === $name && (float)$doc['unit_price'] > 0) {
            return true;
        }
    }
    return false;
});
$free_docs = array_diff($doc_names, $paid_docs);
$notification_parts = [];
if (!empty($paid_docs)) {
    $notification_parts[] = "Payment for " . implode(' and ', $paid_docs) . " via Online";
}
if (!empty($free_docs)) {
    $notification_parts[] = "Submission for " . implode(' and ', $free_docs);
}
$doc_message = implode(' and ', $notification_parts) . " successful. Request ID(s): #" . implode(', #', $request_ids) . ". Awaiting registrar approval.";
$message = $doc_message;
$link = "dashboard.php";
$stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
$stmt->bind_param("iss", $user_id, $message, $link);
if ($stmt->execute()) {
    error_log("Notification created for user $user_id: $message");
} else {
    error_log("Failed to create notification for user $user_id: " . $stmt->error);
}
$stmt->close();

// Notify admins and registrars
$stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('admin', 'registrar')");
$stmt->execute();
$admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$student_name = $user['firstname'] . ' ' . $user['lastname'];
$admin_message = "Student $student_name submitted " . $doc_message;
$admin_link = "/admin/request.php?id=" . $request_ids[0];

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
unset($_SESSION['payment_method']);

// Set success message and redirect to request_document.php
$_SESSION['message'] = "Request successful! " . $doc_message;
$_SESSION['message_type'] = 'success';
header('Location: request_document.php');
exit;
?>