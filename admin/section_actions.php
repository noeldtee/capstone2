<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/capstone-admin/error.log');

require '../config/function.php';

// Restrict to admins
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect('../index.php', 'You must be logged in as an admin to perform this action.', 'danger');
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        if (
            empty($_POST['school_year_id']) || empty($_POST['course_id']) || empty($_POST['year_level']) ||
            empty($_POST['section']) || !isset($_POST['is_active'])
        ) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $school_year_id = (int)validate($_POST['school_year_id']);
        $course_id = (int)validate($_POST['course_id']);
        $year_level = validate($_POST['year_level']);
        $section = validate($_POST['section']);
        $is_active = (int)validate($_POST['is_active']);

        // Validate foreign keys
        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $school_year_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('sections.php', 'Invalid school year selected.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('sections.php', 'Invalid course selected.', 'danger');
        }

        $valid_year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        if (!in_array($year_level, $valid_year_levels)) {
            redirect('sections.php', 'Invalid year level.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM sections WHERE school_year_id = ? AND course_id = ? AND year_level = ? AND section = ?");
        $stmt->bind_param("iiss", $school_year_id, $course_id, $year_level, $section);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'This section already exists.', 'danger');
        }

        $stmt = $conn->prepare("INSERT INTO sections (school_year_id, course_id, year_level, section, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissi", $school_year_id, $course_id, $year_level, $section, $is_active);
        if ($stmt->execute()) {
            $section_id = $stmt->insert_id;
            logAction($conn, 'Section Added', "Section ID: $section_id", "School Year ID: $school_year_id, Course ID: $course_id, Year Level: $year_level, Section: $section");
            redirect('sections.php', 'Section added successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to add section: ' . $stmt->error, 'danger');
        }
        break;

    case 'edit':
        if (
            empty($_POST['id']) || empty($_POST['school_year_id']) || empty($_POST['course_id']) ||
            empty($_POST['year_level']) || empty($_POST['section']) || !isset($_POST['is_active'])
        ) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $school_year_id = (int)validate($_POST['school_year_id']);
        $course_id = (int)validate($_POST['course_id']);
        $year_level = validate($_POST['year_level']);
        $section = validate($_POST['section']);
        $is_active = (int)validate($_POST['is_active']);

        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $school_year_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('sections.php', 'Invalid school year selected.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('sections.php', 'Invalid course selected.', 'danger');
        }

        $valid_year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        if (!in_array($year_level, $valid_year_levels)) {
            redirect('sections.php', 'Invalid year level.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM sections WHERE school_year_id = ? AND course_id = ? AND year_level = ? AND section = ? AND id != ?");
        $stmt->bind_param("iissi", $school_year_id, $course_id, $year_level, $section, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'This section already exists.', 'danger');
        }

        $stmt = $conn->prepare("UPDATE sections SET school_year_id = ?, course_id = ?, year_level = ?, section = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("iissii", $school_year_id, $course_id, $year_level, $section, $is_active, $id);
        if ($stmt->execute()) {
            logAction($conn, 'Section Edited', "Section ID: $id", "School Year ID: $school_year_id, Course ID: $course_id, Year Level: $year_level, Section: $section");
            redirect('sections.php', 'Section updated successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to update section: ' . $stmt->error, 'danger');
        }
        break;

    case 'delete':
        $id = (int)validate($_POST['id']);

        // Check for dependent users
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE section_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($user_count > 0) {
            redirect('sections.php', 'Cannot delete section: There are ' . $user_count . ' users assigned to this section. Please reassign or delete these users first.', 'danger');
        }

        // Fetch section details for logging
        $stmt = $conn->prepare("SELECT school_year_id, course_id, year_level, section FROM sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $section = $result->fetch_assoc();
        $stmt->close();

        // Attempt to delete the section
        $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($section) {
                logAction($conn, 'Section Deleted', "Section ID: $id", "School Year ID: {$section['school_year_id']}, Course ID: {$section['course_id']}, Year Level: {$section['year_level']}, Section: {$section['section']}");
            }
            redirect('sections.php', 'Section deleted successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to delete section: ' . $stmt->error, 'danger');
        }
        break;

    case 'get':
        $id = (int)validate($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = ['status' => 200, 'message' => 'Section fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'Section not found.'];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        break;

    default:
        redirect('sections.php', 'Invalid action.', 'danger');
        break;
}

$conn->close();
