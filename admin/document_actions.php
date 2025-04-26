<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require '../config/function.php';

// Check if user is logged in and authorized
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['registrar', 'staff', 'cashier'])) {
    $_SESSION['message'] = 'Unauthorized access.';
    $_SESSION['message_type'] = 'error';
    header('Location: documents.php');
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
            $is_active = isset($_POST['is_active']) ? (int)validate($_POST['is_active']) : 1;
            $restrict_per_semester = isset($_POST['restrict_per_semester']) ? (int)validate($_POST['restrict_per_semester']) : 0;
            $requirements = validate($_POST['requirements'] ?? '');

            // Validate unit_price
            if (!is_numeric($unit_price) || $unit_price < 0) {
                $_SESSION['message'] = 'Price must be a non-negative number.';
                $_SESSION['message_type'] = 'error';
                header('Location: documents.php');
                exit;
            }
            $unit_price = (float)$unit_price;

            // Check if document name exists
            $stmt = $conn->prepare("SELECT id FROM documents WHERE name = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['message'] = 'Document name already exists.';
                $_SESSION['message_type'] = 'error';
                $stmt->close();
                header('Location: documents.php');
                exit;
            }
            $stmt->close();

            // Insert document
            $stmt = $conn->prepare("INSERT INTO documents (name, description, unit_price, form_needed, is_active, restrict_per_semester, requirements, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssdiiis", $name, $description, $unit_price, $form_needed, $is_active, $restrict_per_semester, $requirements);
            if ($stmt->execute()) {
                $document_id = $stmt->insert_id;
                logAction($conn, "Document Added", "Document ID: $document_id", "Name: $name, Price: ₱$unit_price, Form Needed: " . ($form_needed ? 'Yes' : 'No') . ", Status: " . ($is_active ? 'Active' : 'Inactive') . ", Semester Restriction: " . ($restrict_per_semester ? 'Restricted' : 'Not Restricted') . ", Requirements: " . ($requirements ?: 'None'));
                $_SESSION['message'] = 'Document added successfully.';
                $_SESSION['message_type'] = 'success';
            } else {
                throw new Exception("Failed to add document: " . $stmt->error);
            }
            $stmt->close();
            header('Location: documents.php');
            exit;

        case 'edit':
            // Validate inputs
            $id = isset($_POST['id']) ? (int)validate($_POST['id']) : 0;
            $name = validate($_POST['name'] ?? '');
            $description = validate($_POST['description'] ?? '');
            $unit_price = isset($_POST['unit_price']) ? validate($_POST['unit_price']) : null;
            $form_needed = isset($_POST['form_needed']) ? (int)validate($_POST['form_needed']) : 0;
            $is_active = isset($_POST['is_active']) ? (int)validate($_POST['is_active']) : 1;
            $restrict_per_semester = isset($_POST['restrict_per_semester']) ? (int)validate($_POST['restrict_per_semester']) : 0;
            $requirements = validate($_POST['requirements'] ?? '');

            // Validate unit_price and ID
            if ($id <= 0) {
                $_SESSION['message'] = 'Invalid document ID.';
                $_SESSION['message_type'] = 'error';
                header('Location: documents.php');
                exit;
            }
            if (!is_numeric($unit_price) || $unit_price < 0) {
                $_SESSION['message'] = 'Price must be a non-negative number.';
                $_SESSION['message_type'] = 'error';
                header('Location: documents.php');
                exit;
            }
            $unit_price = (float)$unit_price;

            // Check if document name is used by another document
            $stmt = $conn->prepare("SELECT id FROM documents WHERE name = ? AND id != ?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['message'] = 'Document name already exists.';
                $_SESSION['message_type'] = 'error';
                $stmt->close();
                header('Location: documents.php');
                exit;
            }
            $stmt->close();

            // Update document
            $stmt = $conn->prepare("UPDATE documents SET name = ?, description = ?, unit_price = ?, form_needed = ?, is_active = ?, restrict_per_semester = ?, requirements = ?, updated_at = NOW() 
                                    WHERE id = ?");
            $stmt->bind_param("ssdiiisi", $name, $description, $unit_price, $form_needed, $is_active, $restrict_per_semester, $requirements, $id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    logAction($conn, "Document Edited", "Document ID: $id", "Name: $name, Price: ₱$unit_price, Form Needed: " . ($form_needed ? 'Yes' : 'No') . ", Status: " . ($is_active ? 'Active' : 'Inactive') . ", Semester Restriction: " . ($restrict_per_semester ? 'Restricted' : 'Not Restricted') . ", Requirements: " . ($requirements ?: 'None'));
                    $_SESSION['message'] = 'Document updated successfully.';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'No changes made or document not found.';
                    $_SESSION['message_type'] = 'error';
                }
            } else {
                throw new Exception("Failed to update document: " . $stmt->error);
            }
            $stmt->close();
            header('Location: documents.php');
            exit;

        case 'delete':
            if ($_SESSION['role'] !== 'registrar') {
                $_SESSION['message'] = 'Only registrars can delete documents.';
                $_SESSION['message_type'] = 'error';
                header('Location: documents.php');
                exit;
            }
            $id = isset($_POST['id']) ? (int)validate($_POST['id']) : 0;
            if ($id <= 0) {
                $_SESSION['message'] = 'Invalid document ID.';
                $_SESSION['message_type'] = 'error';
                header('Location: documents.php');
                exit;
            }

            // Fetch document name for logging
            $stmt = $conn->prepare("SELECT name FROM documents WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $_SESSION['message'] = 'Document not found.';
                $_SESSION['message_type'] = 'error';
                $stmt->close();
                header('Location: documents.php');
                exit;
            }
            $name = $result->fetch_assoc()['name'];
            $stmt->close();

            // Check if document is in use
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE document_type = ?");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            if ($count > 0) {
                $_SESSION['message'] = 'Cannot delete document: It is currently in use.';
                $_SESSION['message_type'] = 'error';
                $stmt->close();
                header('Location: documents.php');
                exit;
            }
            $stmt->close();

            // Delete document
            $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    logAction($conn, "Document Deleted", "Document ID: $id", "Name: $name");
                    $_SESSION['message'] = 'Document deleted successfully.';
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Document not found.';
                    $_SESSION['message_type'] = 'error';
                }
            } else {
                throw new Exception("Failed to delete document: " . $stmt->error);
            }
            $stmt->close();
            header('Location: documents.php');
            exit;

        case 'get':
            // This action remains JSON-based for AJAX
            header('Content-Type: application/json');
            $id = isset($_GET['id']) ? (int)validate($_GET['id']) : 0;
            if ($id <= 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid document ID.']);
                exit;
            }
            $stmt = $conn->prepare("SELECT id, name, description, unit_price, form_needed, is_active, restrict_per_semester, requirements 
                                    FROM documents 
                                    WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                // Ensure unit_price is a string formatted to 2 decimals
                $data['unit_price'] = number_format((float)$data['unit_price'], 2, '.', '');
                echo json_encode(['status' => 'success', 'data' => $data]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Document not found.']);
            }
            $stmt->close();
            exit;

        default:
            $_SESSION['message'] = 'Invalid action.';
            $_SESSION['message_type'] = 'error';
            header('Location: documents.php');
            exit;
    }
} catch (Exception $e) {
    error_log("Error in document_actions.php: " . $e->getMessage());
    $_SESSION['message'] = 'An error occurred. Please try again later.';
    $_SESSION['message_type'] = 'error';
    header('Location: documents.php');
    exit;
}
?>