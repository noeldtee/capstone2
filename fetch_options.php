<?php
require 'config/function.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
header('Content-Type: application/json');

switch ($action) {
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
        $school_year_status = isset($_GET['school_year_status']) ? validate($_GET['school_year_status']) : '';

        if (!$school_year_id || !$course_id || !$year_level || !$school_year_status) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
            exit;
        }

        // Fetch sections where status matches the school year's status
        $stmt = $conn->prepare("SELECT id, section FROM sections WHERE school_year_id = ? AND course_id = ? AND year_level = ? AND status = ? ORDER BY section ASC");
        $stmt->bind_param("iiss", $school_year_id, $course_id, $year_level, $school_year_status);
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