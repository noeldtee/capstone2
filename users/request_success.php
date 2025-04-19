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

// Get request IDs and user ID
$request_ids = isset($_GET['request_ids']) ? array_map('intval', explode(',', $_GET['request_ids'])) : [];
$user_id = $_SESSION['user_id'];

if (empty($request_ids)) {
    $_SESSION['message'] = 'No request IDs provided.';
    $_SESSION['message_type'] = 'danger';
    header('Location: request_document.php');
    exit;
}

// Verify requests
$placeholders = implode(',', array_fill(0, count($request_ids), '?'));
$stmt = $conn->prepare("SELECT id, document_type, unit_price, amount, payment_status, payment_link_id FROM requests WHERE id IN ($placeholders) AND user_id = ?");
$params = array_merge($request_ids, [$user_id]);
$types = str_repeat('i', count($request_ids)) . 'i';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (count($requests) !== count($request_ids)) {
    $_SESSION['message'] = 'One or more requests not found.';
    $_SESSION['message_type'] = 'danger';
    header('Location: request_document.php');
    exit;
}

// Log the initial state of requests
error_log("Initial request data: " . json_encode($requests));

// Check if all requests are paid; if not, poll PayMongo API as a fallback
$all_paid = true;
$max_retries = 2;
$retry_delay = 2;

for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
    $all_paid = true;
    foreach ($requests as &$request) {
        // Refresh the request data from the database
        $stmt = $conn->prepare("SELECT payment_status, payment_link_id FROM requests WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $request['id'], $user_id);
        $stmt->execute();
        $updated_request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $request['payment_status'] = $updated_request['payment_status'];
        $request['payment_link_id'] = $updated_request['payment_link_id'];

        if ($request['payment_status'] !== 'paid' && !empty($request['payment_link_id'])) {
            // Log the payment_link_id being used
            error_log("Polling PayMongo API for session {$request['payment_link_id']} (attempt $attempt)");

            // Poll PayMongo API for Checkout Session status
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.paymongo.com/v1/checkout_sessions/" . $request['payment_link_id'],
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
                error_log("PayMongo API poll error for session {$request['payment_link_id']} (attempt $attempt): $err");
                $all_paid = false;
                continue;
            }

            $session_data = json_decode($response, true);
            error_log("PayMongo API response for session {$request['payment_link_id']} (attempt $attempt): " . json_encode($session_data));

            // Check for payment status in the response
            if (isset($session_data['data']['attributes']['status'])) {
                $session_status = $session_data['data']['attributes']['status'];
                $payment_status = $session_data['data']['attributes']['payment_status'] ?? $session_status;
                error_log("Session status: $session_status, Payment status: $payment_status");

                if ($payment_status === 'paid' || $session_status === 'paid') {
                    // Update request to paid
                    $payment_id = $session_data['data']['attributes']['payments'][0]['id'] ?? 'unknown';
                    $stmt = $conn->prepare("UPDATE requests SET payment_status = 'paid', payment_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("sii", $payment_id, $request['id'], $user_id);
                    if ($stmt->execute()) {
                        error_log("Request #{$request['id']} updated to paid via API polling, payment_id: $payment_id");
                        $request['payment_status'] = 'paid'; // Update local array
                    } else {
                        error_log("Failed to update request #{$request['id']} to paid: " . $stmt->error);
                        $all_paid = false;
                    }
                    $stmt->close();
                } else {
                    $all_paid = false;
                    error_log("Payment not confirmed for session {$request['payment_link_id']} (attempt $attempt). Session status: $session_status, Payment status: $payment_status");
                }
            } else {
                $all_paid = false;
                error_log("Invalid PayMongo API response for session {$request['payment_link_id']} (attempt $attempt): " . json_encode($session_data));
            }
        } elseif ($request['payment_status'] !== 'paid') {
            $all_paid = false;
            error_log("Request #{$request['id']} still not paid after attempt $attempt. Current status: {$request['payment_status']}");
        }
    }
    unset($request); // Clean up reference

    if ($all_paid) {
        break; // Exit retry loop if all requests are paid
    }

    if ($attempt < $max_retries) {
        sleep($retry_delay); // Wait before retrying
        error_log("Retrying payment status check (attempt $attempt of $max_retries)...");
    }
}

if (!$all_paid) {
    // One final check in the database in case the webhook updated it
    $all_paid = true;
    foreach ($requests as $request) {
        $stmt = $conn->prepare("SELECT payment_status FROM requests WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $request['id'], $user_id);
        $stmt->execute();
        $final_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($final_check['payment_status'] !== 'paid') {
            $all_paid = false;
            break;
        }
    }

    if (!$all_paid) {
        $_SESSION['message'] = 'Payment not yet confirmed for all requests. Please check again later or verify the payment status in your dashboard.';
        $_SESSION['message_type'] = 'warning';
        header('Location: request_document.php');
        exit;
    }
}

// Create notifications (if not already created by webhook)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND message LIKE ?");
$like_message = "%Payment for document request(s) #" . implode(', #', $request_ids) . "%";
$stmt->bind_param("is", $user_id, $like_message);
$stmt->execute();
$notification_exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
$stmt->close();

if (!$notification_exists) {
    $message = "Payment for document request(s) #" . implode(', #', $request_ids) . " was successful.";
    $link = "dashboard.php";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $user_id, $message, $link);
    $stmt->execute();
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
    $admin_message = "Student $student_name submitted payment for document request(s) #" . implode(', #', $request_ids) . ".";
    $admin_link = "/capstone-admin/admin/request.php?id=" . $request_ids[0];

    foreach ($admins as $admin) {
        $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->bind_param("iss", $admin['id'], $admin_message, $admin_link);
        $stmt->execute();
        $stmt->close();
    }
}

// Set success message and redirect to request_document.php
$_SESSION['message'] = 'Request successful! Payment completed for document request(s) #' . implode(', #', $request_ids) . '.';
$_SESSION['message_type'] = 'success';
header('Location: request_document.php');
exit;