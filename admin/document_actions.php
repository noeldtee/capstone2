<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering
ob_start();

// Include necessary files
require '../config/function.php';

// Set JSON content type
header('Content-Type: application/json');

// Initialize response
$response = ['status' => 'error', 'message' => 'Invalid action.'];

// Check if user is logged in and authorized
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['admin', 'registrar', 'cashier'])) {
    $response = ['status' => 'error', 'message' => 'Unauthorized access.'];
    echo json_encode($response);
    ob_end_flush();
    exit;
}

try {
    // Get the action parameter
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

    switch ($action) {
        case 'add':
            // Validate inputs
            $name = validate($_POST['name'] ?? '');
            $description = validate($_POST['description'] ?? '');
            $unit_price = isset($_POST['unit_price']) ? validate($_POST['unit_price']) : null;
            $form_needed = isset($_POST['form_needed']) ? (int)validate($_POST['form_needed']) : 0;
            $is_active = isset($_POST['form_needed']) ? (int)validate($_POST['is_active']) : 1;
            $restrict_per_semester = isset($_POST['restrict_per_semester']) ? (int)validate($_POST['restrict_per_semester']) : 0;

            // Validate unit_price
            if (!is_numeric($unit_price) || $unit_price < 0) {
                $response = ['status' => 'error', 'message' => 'Price must be a non-negative number.'];
                break;
            }
            $unit_price = (float)$unit_price;

            // Check if document name exists
            $stmt = $conn->prepare("SELECT id FROM documents WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response = ['status' => 'error', 'message' => 'Document name already exists.'];
                $stmt->close();
                break;
            }
            $stmt->close();

            // Insert document
            $stmt = $conn->prepare("INSERT INTO documents (name, description, unit_price, form_needed, is_active, restrict_per_semester, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssdiii", $name, $description, $unit_price, $form_needed, $is_active, $restrict_per_semester);
            if ($stmt->execute()) {
                $document_id = $stmt->insert_id;
                logAction($conn, "Document Added", "Document ID: $document_id", "Name: $name, Price: ₱$unit_price, Form Needed: " . ($form_needed ? 'Yes' : 'No') . ", Status: " . ($is_active ? 'Active' : 'Inactive') . ", Semester Restriction: " . ($restrict_per_semester ? 'Restricted' : 'Not Restricted'));
                $response = ['status' => 'success', 'message' => 'Document added successfully.'];
            } else {
                throw new Exception("Failed to add document: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'edit':
            // Validate inputs
            $id = isset($_POST['id']) ? (int)validate($_POST['id']) : 0;
            $name = validate($_POST['name'] ?? '');
            $description = validate($_POST['description'] ?? '');
            $unit_price = isset($_POST['unit_price']) ? validate($_POST['unit_price']) : null;
            $form_needed = isset($_POST['form_needed']) ? (int)validate($_POST['form_needed']) : 0;
            $is_active = isset($_POST['is_active']) ? (int)validate($_POST['is_active']) : 1;
            $restrict_per_semester = isset($_POST['restrict_per_semester']) ? (int)validate($_POST['restrict_per_semester']) : 0;

            // Validate unit_price and ID
            if ($id <= 0) {
                $response = ['status' => 'error', 'message' => 'Invalid document ID.'];
                break;
            }
            if (!is_numeric($unit_price) || $unit_price < 0) {
                $response = ['status' => 'error', 'message' => 'Price must be a non-negative number.'];
                break;
            }
            $unit_price = (float)$unit_price;

            // Check if document name is used by another document
            $stmt = $conn->prepare("SELECT id FROM documents WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $response = ['status' => 'error', 'message' => 'Document name already exists.'];
                $stmt->close();
                break;
            }
            $stmt->close();

            // Update document
            $stmt = $conn->prepare("UPDATE documents SET name = ?, description = ?, unit_price = ?, form_needed = ?, is_active = ?, restrict_per_semester = ?, updated_at = NOW() 
                                    WHERE id = ?");
            $stmt->bind_param("ssdiiii", $name, $description, $unit_price, $form_needed, $is_active, $restrict_per_semester, $id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    logAction($conn, "Document Edited", "Document ID: $id", "Name: $name, Price: ₱$unit_price, Form Needed: " . ($form_needed ? 'Yes' : 'No') . ", Status: " . ($is_active ? 'Active' : 'Inactive') . ", Semester Restriction: " . ($restrict_per_semester ? 'Restricted' : 'Not Restricted'));
                    $response = ['status' => 'success', 'message' => 'Document updated successfully.'];
                } else {
                    $response = ['status' => 'error', 'message' => 'No changes made or document not found.'];
                }
            } else {
                throw new Exception("Failed to update document: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'delete':
            if ($_SESSION['role'] !== 'admin') {
                $response = ['status' => 'error', 'message' => 'Only admins can delete documents.'];
                break;
            }
            $id = isset($_POST['id']) ? (int)validate($_POST['id']) : 0;
            if ($id <= 0) {
                $response = ['status' => 'error', 'message' => 'Invalid document ID.'];
                break;
            }

            // Fetch document name for logging
            $stmt = $conn->prepare("SELECT name FROM documents WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $response = ['status' => 'error', 'message' => 'Document not found.'];
                $stmt->close();
                break;
            }
            $name = $result->fetch_assoc()['name'];
            $stmt->close();

            // Check if document is in use
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE document_type = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            if ($count > 0) {
                $response = ['status' => 'error', 'message' => 'Cannot delete document: It is currently in use.'];
                $stmt->close();
                break;
            }
            $stmt->close();

            // Delete document
            $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    logAction($conn, "Document Deleted", "Document ID: $id", "Name: $name");
                    $response = ['status' => 'success', 'message' => 'Document deleted successfully.'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Document not found.'];
                }
            } else {
                throw new Exception("Failed to delete document: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'get':
            $id = isset($_GET['id']) ? (int)validate($_GET['id']) : 0;
            if ($id <= 0) {
                $response = ['status' => 'error', 'message' => 'Invalid document ID.'];
                break;
            }
            $stmt = $conn->prepare("SELECT id, name, description, unit_price, form_needed, is_active, restrict_per_semester 
                                    FROM documents 
                                    WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                // Ensure unit_price is a string formatted to 2 decimals
                $data['unit_price'] = number_format((float)$data['unit_price'], 2, '.', '');
                $response = ['status' => 'success', 'data' => $data];
            } else {
                $response = ['status' => 'error', 'message' => 'Document not found.'];
            }
            $stmt->close();
            break;

        default:
            $response = ['status' => 'error', 'message' => 'Invalid action.'];
            break;
    }
} catch (Exception $e) {
    error_log("Error in document_actions.php: " . $e->getMessage());
    $response = ['status' => 'error', 'message' => 'An error occurred. Please try again later.'];
}

// Output JSON response
ob_end_clean();
echo json_encode($response);
exit;
?>