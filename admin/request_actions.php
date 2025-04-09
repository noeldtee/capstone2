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
        case 'archive':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $stmt = $conn->prepare("SELECT request_id, status FROM requests WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $request = $stmt->get_result()->fetch_assoc();

                if ($request) {
                    $stmt = $conn->prepare("UPDATE requests SET archived = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        logAction($conn, 'Archive Request', "Request ID: {$request['request_id']}", "Status: {$request['status']}");
                        $_SESSION['message'] = "Request ID {$request['request_id']} archived successfully.";
                        $_SESSION['message_type'] = "success";
                        $response = ['status' => 'success', 'message' => $_SESSION['message']];
                    } else {
                        throw new Exception("Failed to archive request: " . $stmt->error);
                    }
                } else {
                    $response['message'] = 'Request not found.';
                }
            } else {
                $response['message'] = 'Invalid request ID.';
            }
            break;

        case 'retrieve':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $stmt = $conn->prepare("SELECT request_id, status FROM requests WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $request = $stmt->get_result()->fetch_assoc();

                if ($request) {
                    $stmt = $conn->prepare("UPDATE requests SET archived = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        logAction($conn, 'Retrieve Request', "Request ID: {$request['request_id']}", "Status: {$request['status']}");
                        $_SESSION['message'] = "Request ID {$request['request_id']} retrieved successfully.";
                        $_SESSION['message_type'] = "success";
                        $response = ['status' => 'success', 'message' => $_SESSION['message']];
                    } else {
                        throw new Exception("Failed to retrieve request: " . $stmt->error);
                    }
                } else {
                    $response['message'] = 'Request not found.';
                }
            } else {
                $response['message'] = 'Invalid request ID.';
            }
            break;

        case 'delete':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id > 0) {
                $stmt = $conn->prepare("SELECT request_id, status, file_path FROM requests WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $request = $stmt->get_result()->fetch_assoc();

                if ($request) {
                    if ($request['file_path'] && file_exists("../" . $request['file_path'])) {
                        if (!unlink("../" . $request['file_path'])) {
                            error_log("Failed to delete file for request ID $id: ../{$request['file_path']}");
                        }
                    }
                    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        logAction($conn, 'Delete Request', "Request ID: {$request['request_id']}", "Status: {$request['status']}");
                        $_SESSION['message'] = "Request ID {$request['request_id']} deleted successfully.";
                        $_SESSION['message_type'] = "success";
                        $response = ['status' => 'success', 'message' => $_SESSION['message']];
                    } else {
                        throw new Exception("Failed to delete request: " . $stmt->error);
                    }
                } else {
                    $response['message'] = 'Request not found.';
                }
            } else {
                $response['message'] = 'Invalid request ID.';
            }
            break;

        case 'bulk_archive':
            $ids = isset($_POST['ids']) ? explode(',', $_POST['ids']) : [];
            $ids = array_filter($ids, 'is_numeric');
            $ids = array_map('intval', $ids);

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("SELECT request_id, status FROM requests WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();
                $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                $stmt = $conn->prepare("UPDATE requests SET archived = 1, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                if ($stmt->execute()) {
                    $request_ids = implode(', ', array_column($requests, 'request_id'));
                    logAction($conn, 'Bulk Archive Requests', "Count: " . count($ids), "Request IDs: $request_ids");
                    $_SESSION['message'] = count($ids) . " request(s) archived successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    throw new Exception("Failed to bulk archive requests: " . $stmt->error);
                }
            } else {
                $response['message'] = 'No valid requests selected.';
            }
            break;

        case 'bulk_delete':
            $ids = isset($_POST['ids']) ? explode(',', $_POST['ids']) : [];
            $ids = array_filter($ids, 'is_numeric');
            $ids = array_map('intval', $ids);

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $conn->prepare("SELECT request_id, status, file_path FROM requests WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();
                $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($requests as $request) {
                    if ($request['file_path'] && file_exists("../" . $request['file_path'])) {
                        if (!unlink("../" . $request['file_path'])) {
                            error_log("Failed to delete file for request ID {$request['request_id']}: ../{$request['file_path']}");
                        }
                    }
                }

                $stmt = $conn->prepare("DELETE FROM requests WHERE id IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                if ($stmt->execute()) {
                    $request_ids = implode(', ', array_column($requests, 'request_id'));
                    logAction($conn, 'Bulk Delete Requests', "Count: " . count($ids), "Request IDs: $request_ids");
                    $_SESSION['message'] = count($ids) . " request(s) deleted successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    throw new Exception("Failed to bulk delete requests: " . $stmt->error);
                }
            } else {
                $response['message'] = 'No valid requests selected.';
            }
            break;

        case 'get':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($id > 0) {
                $stmt = $conn->prepare("SELECT r.id, r.request_id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                                               r.price, r.status, r.requested_date, r.file_path, r.remarks, r.rejection_reason 
                                        FROM requests r 
                                        JOIN users u ON r.user_id = u.id 
                                        WHERE r.id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $response = [
                        'status' => 'success',
                        'message' => 'Request fetched successfully.',
                        'data' => $result->fetch_assoc()
                    ];
                } else {
                    $response['message'] = 'Request not found.';
                }
            } else {
                $response['message'] = 'Invalid request ID.';
            }
            break;

        default:
            $response['message'] = 'Invalid action.';
            break;
    }
} catch (Exception $e) {
    error_log("Error in request_actions.php: " . $e->getMessage());
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    $_SESSION['message'] = $response['message'];
    $_SESSION['message_type'] = 'danger';
}

ob_end_clean();
echo json_encode($response);
?>