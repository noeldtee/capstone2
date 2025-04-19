<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/capstone-admin/error.log');

require '../config/function.php';

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
    redirect('../index.php', 'Please log in as an admin or registrar to perform this action.', 'warning');
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        if (empty($_POST['year']) || empty($_POST['status'])) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $year = validate($_POST['year']);
        $status = validate($_POST['status']);

        if (!in_array($status, ['Current', 'Past', 'Inactive'])) {
            redirect('sections.php', 'Invalid status.', 'danger');
        }

        if (!preg_match('/^\d{4}-\d{4}$/', $year)) {
            redirect('sections.php', 'Invalid school year format. Use YYYY-YYYY (e.g., 2024-2025).', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM school_years WHERE year = ?");
        $stmt->bind_param("s", $year);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'School year already exists.', 'danger');
        }
        $stmt->close();

        // If setting to Current, update the previous Current school year to Past
        if ($status === 'Current') {
            $stmt = $conn->prepare("UPDATE school_years SET status = 'Past' WHERE status = 'Current'");
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO school_years (year, status, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $year, $status);
        if ($stmt->execute()) {
            $school_year_id = $stmt->insert_id;
            logAction($conn, 'School Year Added', "School Year ID: $school_year_id", "Year: $year");
            redirect('sections.php', 'School year added successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to add school year: ' . $stmt->error, 'danger');
        }
        break;

    case 'edit':
        if (empty($_POST['id']) || empty($_POST['year']) || empty($_POST['status'])) {
            redirect('sections.php', 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $year = validate($_POST['year']);
        $status = validate($_POST['status']);

        if (!in_array($status, ['Current', 'Past', 'Inactive'])) {
            redirect('sections.php', 'Invalid status.', 'danger');
        }

        if (!preg_match('/^\d{4}-\d{4}$/', $year)) {
            redirect('sections.php', 'Invalid school year format. Use YYYY-YYYY (e.g., 2024-2025).', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM school_years WHERE year = ? AND id != ?");
        $stmt->bind_param("si", $year, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            redirect('sections.php', 'School year already exists.', 'danger');
        }
        $stmt->close();

        // Fetch the current status of this school year
        $stmt = $conn->prepare("SELECT status FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_status = $result->fetch_assoc()['status'];
        $stmt->close();

        // If setting to Current, update the previous Current school year to Past
        if ($status === 'Current' && $current_status !== 'Current') {
            $stmt = $conn->prepare("UPDATE school_years SET status = 'Past' WHERE status = 'Current'");
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare("UPDATE school_years SET year = ?, status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $year, $status, $id);
        if ($stmt->execute()) {
            logAction($conn, 'School Year Edited', "School Year ID: $id", "Year: $year");
            redirect('sections.php', 'School year updated successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to update school year: ' . $stmt->error, 'danger');
        }
        break;

    case 'delete':
        $id = (int)validate($_POST['id']);

        // Check for dependent users
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE year_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_count = $result->fetch_assoc()['count'];
        $stmt->close();

        // Check for dependent sections
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sections WHERE school_year_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $section_count = $result->fetch_assoc()['count'];
        $stmt->close();

        if ($user_count > 0 || $section_count > 0) {
            $message = 'Cannot delete school year: ';
            if ($user_count > 0) {
                $message .= "There are $user_count users assigned to this school year. ";
            }
            if ($section_count > 0) {
                $message .= "There are $section_count sections linked to this school year. ";
            }
            $message .= 'Please reassign or delete these records first.';
            redirect('sections.php', $message, 'danger');
        }

        // Fetch school year details for logging
        $stmt = $conn->prepare("SELECT year FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $school_year = $result->fetch_assoc();
        $stmt->close();

        // Attempt to delete the school year
        $stmt = $conn->prepare("DELETE FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($school_year) {
                logAction($conn, 'School Year Deleted', "School Year ID: $id", "Year: {$school_year['year']}");
            }
            redirect('sections.php', 'School year deleted successfully.', 'success');
        } else {
            redirect('sections.php', 'Failed to delete school year: ' . $stmt->error, 'danger');
        }
        break;

    case 'get':
        $id = (int)validate($_GET['id']);
        $stmt = $conn->prepare("SELECT * FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = ['status' => 200, 'message' => 'School year fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'School year not found.'];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        break;

    default:
        redirect('sections.php', 'Invalid action.', 'danger');
        break;
}

$conn->close();