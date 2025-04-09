<?php
require 'config/function.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'courses':
        $stmt = $conn->prepare("SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name ASC");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . $conn->error]);
            exit;
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $courses = [];
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
        if (empty($courses)) {
            echo json_encode(['status' => 'success', 'courses' => [], 'message' => 'No active courses found']);
        } else {
            echo json_encode(['status' => 'success', 'courses' => $courses]);
        }
        $stmt->close();
        break;

    case 'sections':
        $course_id = (int)($_GET['course_id'] ?? 0);
        $year_level = $_GET['year_level'] ?? '';
        if ($course_id <= 0 || empty($year_level)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid course ID or year level']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, section FROM sections WHERE course_id = ? AND year_level = ? AND is_active = 1 ORDER BY section ASC");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'Query preparation failed: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("is", $course_id, $year_level);
        $stmt->execute();
        $result = $stmt->get_result();
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
        if (empty($sections)) {
            echo json_encode(['status' => 'success', 'sections' => [], 'message' => 'No active sections found']);
        } else {
            echo json_encode(['status' => 'success', 'sections' => $sections]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}

$conn->close();