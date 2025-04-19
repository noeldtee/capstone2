<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/capstone-admin/error.log');

require '../config/function.php';

// Allow admin and registrar for most actions, except delete
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
    if (isset($_GET['action']) && in_array($_GET['action'], ['get', 'get_sections'])) {
        // For AJAX requests, return JSON instead of redirecting
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 401, 'message' => 'Unauthorized access.']);
        exit;
    }
    redirect('../index.php', 'You must be logged in as an admin or registrar to perform this action.', 'danger');
}

// Restrict delete to admin only
if (isset($_POST['action']) && $_POST['action'] === 'delete' && $_SESSION['role'] !== 'admin') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 403, 'message' => 'Only admins can delete users.']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        if (
            empty($_POST['studentid']) || empty($_POST['firstname']) || empty($_POST['lastname']) ||
            empty($_POST['email']) || empty($_POST['password']) || empty($_POST['course_id']) ||
            empty($_POST['section_id']) || empty($_POST['year_id']) || empty($_POST['year_level']) ||
            empty($_POST['role']) || !isset($_POST['is_ban']) || !isset($_POST['terms'])
        ) {
            redirect('students.php?' . http_build_query($_GET), 'All fields are required.', 'danger');
        }

        $studentid = validate($_POST['studentid']);
        $firstname = validate($_POST['firstname']);
        $middlename = validate($_POST['middlename'] ?? '');
        $lastname = validate($_POST['lastname']);
        $email = validate($_POST['email']);
        $password = validate($_POST['password']);
        $profile = '../assets/images/default_profile.png';
        $course_id = (int)validate($_POST['course_id']);
        $section_id = (int)validate($_POST['section_id']);
        $year_id = (int)validate($_POST['year_id']);
        $year_level = validate($_POST['year_level']);
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);
        $terms = isset($_POST['terms']) ? 1 : 0;

        if (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
            redirect('students.php?' . http_build_query($_GET), 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE studentid = ?");
        $stmt->bind_param("s", $studentid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Student ID already exists.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Email already exists.', 'danger');
        }
        $stmt->close();

        if (!in_array($year_level, ['1st Year', '2nd Year', '3rd Year', '4th Year'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid year level.', 'danger');
        }

        if (!in_array($role, ['student', 'alumni'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid role.', 'danger');
        }

        if (!in_array($is_ban, [0, 1])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid status.', 'danger');
        }

        if ($terms !== 1) {
            redirect('students.php?' . http_build_query($_GET), 'You must accept the terms of service.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid course selected.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid section selected.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $year_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid school year selected.', 'danger');
        }
        $stmt->close();

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (studentid, firstname, middlename, lastname, email, profile, password, course_id, section_id, year_id, year_level, role, terms, is_ban, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssiisssii", $studentid, $firstname, $middlename, $lastname, $email, $profile, $hashed_password, $course_id, $section_id, $year_id, $year_level, $role, $terms, $is_ban);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $log_success = logAction($conn, "Student Added", "User ID: $user_id", "Student ID: $studentid, Name: $firstname $middlename $lastname, Role: $role", $_SERVER['REMOTE_ADDR']);
            if (!$log_success) {
                error_log("Failed to log action for adding user ID: $user_id");
            }
            redirect('students.php?' . http_build_query($_GET), 'User added successfully.', 'success');
        } else {
            error_log("Failed to add user: " . $stmt->error);
            redirect('students.php?' . http_build_query($_GET), 'Failed to add user: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'edit':
        if (
            empty($_POST['id']) || empty($_POST['studentid']) || empty($_POST['firstname']) ||
            empty($_POST['lastname']) || empty($_POST['email']) || empty($_POST['course_id']) ||
            empty($_POST['section_id']) || empty($_POST['year_id']) || empty($_POST['year_level']) ||
            empty($_POST['role']) || !isset($_POST['is_ban'])
        ) {
            redirect('students.php?' . http_build_query($_GET), 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $studentid = validate($_POST['studentid']);
        $firstname = validate($_POST['firstname']);
        $middlename = validate($_POST['middlename'] ?? '');
        $lastname = validate($_POST['lastname']);
        $email = validate($_POST['email']);
        $profile = '../assets/images/default_profile.png';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $course_id = (int)validate($_POST['course_id']);
        $section_id = (int)validate($_POST['section_id']);
        $year_id = (int)validate($_POST['year_id']);
        $year_level = validate($_POST['year_level']);
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);
        $terms = isset($_POST['terms']) ? 1 : 0;

        if (!empty($password) && (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password))) {
            redirect('students.php?' . http_build_query($_GET), 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE studentid = ? AND id != ?");
        $stmt->bind_param("si", $studentid, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Student ID already exists.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Email already exists.', 'danger');
        }
        $stmt->close();

        if (!in_array($year_level, ['1st Year', '2nd Year', '3rd Year', '4th Year'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid year level.', 'danger');
        }

        if (!in_array($role, ['student', 'alumni'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid role.', 'danger');
        }

        if (!in_array($is_ban, [0, 1])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid status.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid course selected.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid section selected.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $year_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid school year selected.', 'danger');
        }
        $stmt->close();

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET studentid = ?, firstname = ?, middlename = ?, lastname = ?, email = ?, profile = ?, password = ?, course_id = ?, section_id = ?, year_id = ?, year_level = ?, role = ?, terms = ?, is_ban = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssssiisssiii", $studentid, $firstname, $middlename, $lastname, $email, $profile, $hashed_password, $course_id, $section_id, $year_id, $year_level, $role, $terms, $is_ban, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET studentid = ?, firstname = ?, middlename = ?, lastname = ?, email = ?, profile = ?, course_id = ?, section_id = ?, year_id = ?, year_level = ?, role = ?, terms = ?, is_ban = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssssiisssiii", $studentid, $firstname, $middlename, $lastname, $email, $profile, $course_id, $section_id, $year_id, $year_level, $role, $terms, $is_ban, $id);
        }

        if ($stmt->execute()) {
            $log_success = logAction($conn, "Student Edited", "User ID: $id", "Student ID: $studentid, Name: $firstname $middlename $lastname, Role: $role", $_SERVER['REMOTE_ADDR']);
            if (!$log_success) {
                error_log("Failed to log action for editing user ID: $id");
            }
            redirect('students.php?' . http_build_query($_GET), 'User updated successfully.', 'success');
        } else {
            error_log("Failed to update user: " . $stmt->error);
            redirect('students.php?' . http_build_query($_GET), 'Failed to update user: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'delete':
        if (!isset($_POST['id']) || !is_numeric($_POST['id']) || (int)$_POST['id'] <= 0) {
            error_log("Invalid or missing user ID for deletion: " . (isset($_POST['id']) ? $_POST['id'] : 'not set'));
            redirect('students.php?' . http_build_query($_GET), 'Invalid user ID.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $stmt = $conn->prepare("SELECT studentid, firstname, middlename, lastname, role FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            if ($user) {
                $log_success = logAction($conn, "Student Deleted", "User ID: $id", "Student ID: {$user['studentid']}, Name: {$user['firstname']} {$user['middlename']} {$user['lastname']}, Role: {$user['role']}", $_SERVER['REMOTE_ADDR']);
                if (!$log_success) {
                    error_log("Failed to log action for deleting user ID: $id");
                }
            }
            redirect('students.php?' . http_build_query($_GET), 'User deleted successfully.', 'success');
        } else {
            if ($stmt->error) {
                error_log("Failed to delete user ID $id: " . $stmt->error);
                redirect('students.php?' . http_build_query($_GET), 'Failed to delete user: ' . $stmt->error, 'danger');
            } else {
                error_log("No user found with ID $id for deletion.");
                redirect('students.php?' . http_build_query($_GET), 'User not found.', 'danger');
            }
        }
        $stmt->close();
        break;

    case 'get':
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        ini_set('display_errors', 0);
        error_reporting(E_ALL);

        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            $response = ['status' => 400, 'message' => 'Invalid user ID.'];
            header('Content-Type: application/json');
            echo json_encode($response);
            ob_end_flush();
            exit;
        }

        $id = (int)validate($_GET['id']);
        if (!$conn) {
            $response = ['status' => 500, 'message' => 'Database connection failed.'];
            header('Content-Type: application/json');
            echo json_encode($response);
            ob_end_flush();
            exit;
        }

        $stmt = $conn->prepare("SELECT id, studentid, firstname, middlename, lastname, email, profile, course_id, section_id, year_id, year_level, role, terms, is_ban FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = ['status' => 200, 'message' => 'User fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'User not found.'];
        }

        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode($response);
        ob_end_flush();
        exit;

    case 'get_sections':
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        ini_set('display_errors', 0);
        error_reporting(E_ALL);

        $course_id = isset($_GET['course_id']) && is_numeric($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
        $school_year_id = isset($_GET['school_year_id']) && is_numeric($_GET['school_year_id']) ? (int)$_GET['school_year_id'] : 0;

        if (!$course_id || !$school_year_id) {
            $response = ['status' => 400, 'message' => 'Invalid course or school year ID.'];
            header('Content-Type: application/json');
            echo json_encode($response);
            ob_end_flush();
            exit;
        }

        if (!$conn) {
            $response = ['status' => 500, 'message' => 'Database connection failed.'];
            header('Content-Type: application/json');
            echo json_encode($response);
            ob_end_flush();
            exit;
        }

        $stmt = $conn->prepare("SELECT s.id, s.section, c.name AS course_name, sy.year AS school_year 
                                FROM sections s 
                                JOIN courses c ON s.course_id = c.id 
                                JOIN school_years sy ON s.school_year_id = sy.id 
                                WHERE s.course_id = ? AND s.school_year_id = ?");
        $stmt->bind_param("ii", $course_id, $school_year_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $sections = [];
        while ($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }

        $stmt->close();
        $response = ['status' => 200, 'message' => 'Sections fetched successfully.', 'data' => $sections];
        header('Content-Type: application/json');
        echo json_encode($response);
        ob_end_flush();
        exit;
}

redirect('students.php?' . http_build_query($_GET), 'Invalid action.', 'danger');
?>