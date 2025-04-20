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
        case 'archive':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE requests SET archived = 1, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Archive Request', "Request ID: $id", "Request archived");
                $_SESSION['message'] = "Request ID $id archived successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to archive request or request not found.'];
            }
            $stmt->close();
            break;

        case 'bulk_archive':
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("UPDATE requests SET archived = 1, updated_at = NOW() WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Archive Requests', "Count: $affected", "Request IDs: $request_ids");
                    $_SESSION['message'] = "$affected request(s) archived successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'No requests were archived.'];
                }
            } else {
                throw new Exception("Failed to bulk archive requests: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'retrieve':
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE requests SET archived = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                logAction($conn, 'Retrieve Request', "Request ID: $id", "Request retrieved from archive");
                $_SESSION['message'] = "Request ID $id retrieved successfully.";
                $_SESSION['message_type'] = "success";
                $response = ['status' => 'success', 'message' => $_SESSION['message']];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to retrieve request or request not found.'];
            }
            $stmt->close();
            break;

        case 'delete':
            if ($_SESSION['role'] !== 'admin') {
                $response = ['status' => 'error', 'message' => 'Only admins can delete requests.'];
                break;
            }
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("SELECT file_path FROM requests WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $file_path = $row['file_path'];

                $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    if ($file_path && file_exists("../$file_path")) {
                        unlink("../$file_path");
                    }
                    logAction($conn, 'Delete Request', "Request ID: $id", "Request deleted");
                    $_SESSION['message'] = "Request ID $id deleted successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to delete request.'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Request not found.'];
            }
            $stmt->close();
            break;

        case 'bulk_delete':
            if ($_SESSION['role'] !== 'admin') {
                $response = ['status' => 'error', 'message' => 'Only admins can delete requests.'];
                break;
            }
            $ids = array_filter(explode(',', $_POST['ids']), 'is_numeric');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $response = ['status' => 'error', 'message' => 'No valid requests selected.'];
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $conn->prepare("SELECT id, file_path FROM requests WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            $stmt->execute();
            $result = $stmt->get_result();
            $files_to_delete = [];
            while ($row = $result->fetch_assoc()) {
                if ($row['file_path']) {
                    $files_to_delete[$row['id']] = $row['file_path'];
                }
            }

            $stmt = $conn->prepare("DELETE FROM requests WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                if ($affected > 0) {
                    foreach ($files_to_delete as $file_path) {
                        if ($file_path && file_exists("../$file_path")) {
                            unlink("../$file_path");
                        }
                    }
                    $request_ids = implode(', ', $ids);
                    logAction($conn, 'Bulk Delete Requests', "Count: $affected", "Request IDs: $request_ids");
                    $_SESSION['message'] = "$affected request(s) deleted successfully.";
                    $_SESSION['message_type'] = "success";
                    $response = ['status' => 'success', 'message' => $_SESSION['message']];
                } else {
                    $response = ['status' => 'error', 'message' => 'No requests were deleted.'];
                }
            } else {
                throw new Exception("Failed to bulk delete requests: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'get':
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("
                SELECT r.id, r.document_type, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
                       r.unit_price, r.status, r.requested_date, r.file_path, r.remarks, r.rejection_reason, 
                       r.payment_status, u.email, u.number, u.year_level, 
                       sy.year AS school_year, c.name AS course_name, s.section AS section_name
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

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action.'];
            break;
    }
} catch (Exception $e) {
    error_log("Error in request_actions.php: " . $e->getMessage());
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