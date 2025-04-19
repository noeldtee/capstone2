<?php
require 'config/function.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
header('Content-Type: application/json');

switch ($action) {
    case 'school_years':
        $role = isset($_GET['role']) ? validate($_GET['role']) : '';
        if (!in_array($role, ['student', 'alumni'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid role specified.']);
            exit;
        }
        $status = $role === 'student' ? 'Current' : 'Past';
        $stmt = $conn->prepare("SELECT id, year FROM school_years WHERE status = ? ORDER BY year DESC");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $school_years = [];
        while ($row = $result->fetch_assoc()) {
            $school_years[] = $row;
        }
        $stmt->close();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'success', 'school_years' => $school_years]);
        } else {
            echo json_encode(['status' => 'success', 'school_years' => [], 'message' => 'No school years found for the selected role.']);
        }
        break;

    case 'courses':
        // Fetch only active courses
        $stmt = $conn->prepare("SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        $stmt->close();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'success', 'courses' => $courses]);
        } else {
            echo json_encode(['status' => 'success', 'courses' => [], 'message' => 'No active courses found.']);
        }
        break;

    case 'sections':
        $school_year_id = isset($_GET['school_year_id']) ? (int)$_GET['school_year_id'] : 0;
        $course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
        $year_level = isset($_GET['year_level']) ? validate($_GET['year_level']) : '';

        // Validate inputs
        if (!$school_year_id || !$course_id || !$year_level || !in_array($year_level, ['1st Year', '2nd Year', '3rd Year', '4th Year'])) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid required parameters.']);
            exit;
        }

        // Fetch sections for the selected school year, ensuring status matches role
        $stmt = $conn->prepare("
            SELECT s.id, s.section 
            FROM sections s 
            JOIN school_years sy ON s.school_year_id = sy.id 
            WHERE s.school_year_id = ? 
            AND s.course_id = ? 
            AND s.year_level = ? 
            AND sy.status IN ('Current', 'Past')
            ORDER BY s.section ASC
        ");
        $stmt->bind_param("iis", $school_year_id, $course_id, $year_level);
        $stmt->execute();
        $result = $stmt->get_result();
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
        $stmt->close();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'success', 'sections' => $sections]);
        } else {
            echo json_encode(['status' => 'success', 'sections' => [], 'message' => 'No sections found for the selected criteria.']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}

$conn->close();
?>