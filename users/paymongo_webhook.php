<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/function.php';

header('Content-Type: application/json');

// Log the raw request for debugging
$raw_input = file_get_contents('php://input');
error_log("Raw webhook input: " . $raw_input);

// Read the incoming webhook payload
$payload = $raw_input;
$event = json_decode($payload, true);

// Validate the webhook event
if (!isset($event['data']['attributes']['type'])) {
    error_log("Invalid webhook payload: " . $payload);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid webhook payload']);
    exit;
}

$type = $event['data']['attributes']['type'];

// Log event for debugging
error_log("Webhook received: " . print_r($event, true));

// Handle payment.paid event
if ($type === 'payment.paid') {
    $payment_id = $event['data']['attributes']['data']['id'];
    $amount = $event['data']['attributes']['data']['attributes']['amount'];
    $metadata = $event['data']['attributes']['data']['attributes']['metadata'] ?? [];
    $request_ids = $metadata['request_ids'] ?? [];
    $user_id = (int)($metadata['user_id'] ?? 0);

    if (empty($request_ids) || $user_id <= 0) {
        error_log("Missing metadata: request_ids=" . json_encode($request_ids) . ", user_id=$user_id");
        http_response_code(400);
        echo json_encode(['error' => 'Missing metadata']);
        exit;
    }

    // Update each request
    $stmt = $conn->prepare("UPDATE requests SET payment_status = 'paid', payment_id = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND payment_status = 'pending'");
    foreach ($request_ids as $request_id) {
        $stmt->bind_param("sii", $payment_id, $request_id, $user_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                error_log("Request #$request_id updated to paid, payment_id: $payment_id");
            } else {
                error_log("No matching request found for ID $request_id, user_id $user_id, or already updated");
            }
        } else {
            error_log("Failed to update request #$request_id: " . $stmt->error);
        }
    }
    $stmt->close();

    // Create notification for student
    $message = "Payment for document request(s) #" . implode(', #', $request_ids) . " was successful.";
    $link = "users/request_document.php";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("iss", $user_id, $message, $link);
    if ($stmt->execute()) {
        error_log("Notification created for user $user_id: $message");
    } else {
        error_log("Failed to create notification for user $user_id: " . $stmt->error);
    }
    $stmt->close();

    // Create notifications for admins
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare("SELECT document_type FROM requests WHERE id IN (" . implode(',', array_fill(0, count($request_ids), '?')) . ")");
    foreach ($request_ids as $i => $id) {
        $stmt->bind_param(str_repeat('i', count($request_ids))[$i], $id);
    }
    $stmt->execute();
    $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $doc_names = array_column($docs, 'document_type');
    $admin_message = "Student submitted payment for document request(s) #" . implode(', #', $request_ids) . ": " . implode(', ', $doc_names) . ".";
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
}

// Handle payment.failed event
if ($type === 'payment.failed') {
    $metadata = $event['data']['attributes']['data']['attributes']['metadata'] ?? [];
    $request_ids = $metadata['request_ids'] ?? [];
    $user_id = (int)($metadata['user_id'] ?? 0);

    if (!empty($request_ids) && $user_id > 0) {
        $stmt = $conn->prepare("UPDATE requests SET payment_status = 'failed', updated_at = NOW() WHERE id = ? AND user_id = ?");
        foreach ($request_ids as $request_id) {
            $stmt->bind_param("ii", $request_id, $user_id);
            if ($stmt->execute()) {
                error_log("Request #$request_id marked as failed");
            } else {
                error_log("Failed to mark request #$request_id as failed: " . $stmt->error);
            }
        }
        $stmt->close();

        $message = "Payment for document request(s) #" . implode(', #', $request_ids) . " failed. Please try again.";
        $link = "users/request_document.php";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->bind_param("iss", $user_id, $message, $link);
        if ($stmt->execute()) {
            error_log("Notification created for user $user_id: $message");
        } else {
            error_log("Failed to create notification for user $user_id: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Missing metadata in failed payment: request_ids=" . json_encode($request_ids) . ", user_id=$user_id");
    }
}

http_response_code(200);
echo json_encode(['status' => 'success']);