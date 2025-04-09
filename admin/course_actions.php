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
        if (empty($_POST['name']) || empty($_POST['code']) || !isset($_POST['is_active'])) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $name = validate($_POST['name']);
        $code = validate($_POST['code']);
        $description = validate($_POST['description']);
        $is_active = (int)validate($_POST['is_active']);

        $stmt = $conn->prepare("SELECT id FROM courses WHERE code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'Course code already exists.', 'danger');
        }

        $stmt = $conn->prepare("INSERT INTO courses (name, code, description, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $name, $code, $description, $is_active);
        if ($stmt->execute()) {
            $course_id = $stmt->insert_id;
            logAction($conn, 'Course Added', "Course ID: $course_id", "Name: $name, Code: $code");
            redirect('sections.php', 'Course added successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to add course: ' . $stmt->error, 'danger');
        }
        break;

    case 'edit':
        if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['code']) || !isset($_POST['is_active'])) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $name = validate($_POST['name']);
        $code = validate($_POST['code']);
        $description = validate($_POST['description']);
        $is_active = (int)validate($_POST['is_active']);

        $stmt = $conn->prepare("SELECT id FROM courses WHERE code = ? AND id != ?");
        $stmt->bind_param("si", $code, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'Course code already exists.', 'danger');
        }

        $stmt = $conn->prepare("UPDATE courses SET name = ?, code = ?, description = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("sssii", $name, $code, $description, $is_active, $id);
        if ($stmt->execute()) {
            logAction($conn, 'Course Edited', "Course ID: $id", "Name: $name, Code: $code");
            redirect('sections.php', 'Course updated successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to update course: ' . $stmt->error, 'danger');
        }
        break;

    case 'delete':
        $id = (int)validate($_POST['id']);

        // Check for dependent sections
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sections WHERE course_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $section_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($section_count > 0) {
            redirect('sections.php', 'Cannot delete course: There are ' . $section_count . ' sections linked to this course. Please delete or reassign these sections first.', 'danger');
        }

        // Fetch course details for logging
        $stmt = $conn->prepare("SELECT name, code FROM courses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course = $result->fetch_assoc();
        $stmt->close();

        // Attempt to delete the course
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($course) {
                logAction($conn, 'Course Deleted', "Course ID: $id", "Name: {$course['name']}, Code: {$course['code']}");
            }
            redirect('sections.php', 'Course deleted successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to delete course: ' . $stmt->error, 'danger');
        }
        break;

    case 'get':
        $id = (int)validate($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = ['status' => 200, 'message' => 'Course fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'Course not found.'];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        break;

    default:
        redirect('sections.php', 'Invalid action.', 'danger');
        break;
}

$conn->close();