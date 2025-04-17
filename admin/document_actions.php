<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../config/function.php';

// Handle the action based on the request
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        // Add new document
        $name = validate($_POST['name']);
        $description = validate($_POST['description']);
        $price = validate($_POST['price']);
        $form_needed = validate($_POST['form_needed']);
        $is_active = validate($_POST['is_active']);

        // Check if document name already exists
        $stmt = $conn->prepare("SELECT id FROM documents WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('documents.php', 'Document name already exists.', 'danger');
        }

        // Insert new document
        $stmt = $conn->prepare("INSERT INTO documents (name, description, price, form_needed, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdii", $name, $description, $price, $form_needed, $is_active);
        if ($stmt->execute()) {
            $document_id = $stmt->insert_id;
            logAction($conn, "Document Added", "Document ID: $document_id", "Name: $name, Price: ₱$price, Form Needed: " . ($form_needed ? 'Yes' : 'No') . ", Status: " . ($is_active ? 'Active' : 'Inactive'));
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Document added successfully.', 'success');
        } else {
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Failed to add document: ' . $conn->error, 'danger');
        }
        break;

    case 'edit':
        // Edit existing document
        $id = validate($_POST['id']);
        $name = validate($_POST['name']);
        $description = validate($_POST['description']);
        $price = validate($_POST['price']);
        $form_needed = validate($_POST['form_needed']);
        $is_active = validate($_POST['is_active']);

        // Check if document name is already used by another document
        $stmt = $conn->prepare("SELECT id FROM documents WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Document name already exists.', 'danger');
        }

        // Update document
        $stmt = $conn->prepare("UPDATE documents SET name = ?, description = ?, price = ?, form_needed = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssdiii", $name, $description, $price, $form_needed, $is_active, $id);
        if ($stmt->execute()) {
            logAction($conn, "Document Edited", "Document ID: $id", "Name: $name, Price: ₱$price, Form Needed: " . ($form_needed ? 'Yes' : 'No') . ", Status: " . ($is_active ? 'Active' : 'Inactive'));
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Document updated successfully.', 'success');
        } else {
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Failed to update document: ' . $conn->error, 'danger');
        }
        break;

    case 'delete':
        // Delete document
        $id = validate($_POST['id']);

        // Fetch document name for logging and checking
        $stmt = $conn->prepare("SELECT name FROM documents WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Document not found.', 'danger');
        }
        $doc = $result->fetch_assoc();
        $name = $doc['name'];

        // Check if the document is used in requests (using document_type matching name)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE document_type = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result['count'] > 0) {
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Cannot delete document: It is currently in use.', 'danger');
        }

        // Delete document
        $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            logAction($conn, "Document Deleted", "Document ID: $id", "Name: $name");
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Document deleted successfully.', 'success');
        } else {
            redirect('documents.php?page=' . (isset($_GET['page']) ? $_GET['page'] : 1), 'Failed to delete document: ' . $conn->error, 'danger');
        }
        break;

    case 'get':
        // Fetch document data for edit modal
        $id = validate($_GET['id']);
        $response = getById('documents', $id);
        header('Content-Type: application/json');
        echo json_encode($response);
        break;

    default:
        redirect('documents.php', 'Invalid action.', 'danger');
        break;
}
?>