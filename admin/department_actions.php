<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/capstone-admin/error.log');

require '../config/function.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect('../index.php', 'You must be logged in as an admin to perform this action.', 'danger');
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        if (empty($_POST['name']) || !isset($_POST['is_active'])) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $name = validate($_POST['name']);
        $is_active = (int)validate($_POST['is_active']);

        $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'Department name already exists.', 'danger');
        }

        $stmt = $conn->prepare("INSERT INTO departments (name, is_active, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("si", $name, $is_active);
        if ($stmt->execute()) {
            $department_id = $stmt->insert_id;
            logAction($conn, 'Department Added', "Department ID: $department_id", "Name: $name");
            redirect('sections.php', 'Department added successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to add department: ' . $stmt->error, 'danger');
        }
        break;

    case 'edit':
        if (empty($_POST['id']) || empty($_POST['name']) || !isset($_POST['is_active'])) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $name = validate($_POST['name']);
        $is_active = (int)validate($_POST['is_active']);

        $stmt = $conn->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'Department name already exists.', 'danger');
        }

        $stmt = $conn->prepare("UPDATE departments SET name = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sii", $name, $is_active, $id);
        if ($stmt->execute()) {
            logAction($conn, 'Department Edited', "Department ID: $id", "Name: $name");
            redirect('sections.php', 'Department updated successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to update department: ' . $stmt->error, 'danger');
        }
        break;

    case 'delete':
        $id = (int)validate($_POST['id']);

        // Check for dependent courses
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE department_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($course_count > 0) {
            redirect('sections.php', 'Cannot delete department: There are ' . $course_count . ' courses linked to this department. Please delete or reassign these courses first.', 'danger');
        }

        // Fetch department details for logging
        $stmt = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $department = $result->fetch_assoc();
        $stmt->close();

        // Attempt to delete the department
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($department) {
                logAction($conn, 'Department Deleted', "Department ID: $id", "Name: {$department['name']}");
            }
            redirect('sections.php', 'Department deleted successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to delete department: ' . $stmt->error, 'danger');
        }
        break;

    case 'get':
        $id = (int)validate($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = ['status' => 200, 'message' => 'Department fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'Department not found.'];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        break;

    default:
        redirect('sections.php', 'Invalid action.', 'danger');
        break;
}

$conn->close();
