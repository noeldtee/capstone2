<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/capstone-admin/error.log');

require '../config/function.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

// Allow admin and registrar for most actions, except edit and delete
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

// Restrict edit to admin only
if (isset($_POST['action']) && $_POST['action'] === 'edit' && $_SESSION['role'] !== 'admin') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 403, 'message' => 'Only admins can edit users.']);
    exit;
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
        $course_id = (int)validate($_POST['course_id']);
        $section_id = (int)validate($_POST['section_id']);
        $year_id = (int)validate($_POST['year_id']);
        $year_level = validate($_POST['year_level']);
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);
        $terms = isset($_POST['terms']) ? 1 : 0;

        // Validate student ID format (must start with "MA")
        if (!preg_match('/^MA[0-9]+$/', $studentid)) {
            redirect('students.php?' . http_build_query($_GET), 'Student ID must start with "MA" followed by numbers (e.g., MA1231232).', 'danger');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid email format.', 'danger');
        }

        // Validate password
        if (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
            redirect('students.php?' . http_build_query($_GET), 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        // Check if student ID already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE studentid = ?");
        $stmt->bind_param("s", $studentid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Student ID already exists.', 'danger');
        }
        $stmt->close();

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Email already exists.', 'danger');
        }
        $stmt->close();

        // Validate year level
        if (!in_array($year_level, ['1st Year', '2nd Year', '3rd Year', '4th Year'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid year level.', 'danger');
        }

        // Validate role
        if (!in_array($role, ['student', 'alumni'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid role.', 'danger');
        }

        // Validate status
        if (!in_array($is_ban, [0, 1])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid status.', 'danger');
        }

        // Validate terms
        if ($terms !== 1) {
            redirect('students.php?' . http_build_query($_GET), 'You must accept the terms of service.', 'danger');
        }

        // Validate course
        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid course selected.', 'danger');
        }
        $stmt->close();

        // Validate section
        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid section selected.', 'danger');
        }
        $stmt->close();

        // Validate school year
        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $year_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid school year selected.', 'danger');
        }
        $stmt->close();

        // Generate verification token and expiry (1 day)
        $verify_token = bin2hex(random_bytes(16));
        $verify_expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
        $verify_status = 0; // Unverified

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into database with profile set to NULL
        $stmt = $conn->prepare("
            INSERT INTO users (studentid, firstname, middlename, lastname, email, profile, password, course_id, section_id, year_id, year_level, role, terms, is_ban, verify_status, verify_token, verify_expires_at, created_at)
            VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("ssssssiisssiisss", $studentid, $firstname, $middlename, $lastname, $email, $hashed_password, $course_id, $section_id, $year_id, $year_level, $role, $terms, $is_ban, $verify_status, $verify_token, $verify_expires_at);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $log_success = logAction($conn, "Student Added", "User ID: $user_id", "Student ID: $studentid, Name: $firstname $middlename $lastname, Role: $role", $_SERVER['REMOTE_ADDR']);
            if (!$log_success) {
                error_log("Failed to log action for adding user ID: $user_id");
            }

            // Send verification email
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->SMTPAuth = true;
                $mail->Host = 'smtp.gmail.com';
                $mail->Username = 'bpcregistrar75@gmail.com';
                $mail->Password = 'nkei hmzy qpwn wzch';
                $mail->SMTPSecure = "tls";
                $mail->Port = 587;
                $mail->setFrom('bpcregistrar75@gmail.com', 'Bulacan Polytechnic College Registrar');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Email Verification from Bulacan Polytechnic College Registrar';
                $mail->Body = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
        <!-- Header -->
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #2e7d32; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                <img src="https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/assets/images/logo.png" alt="BPC Logo" width="80" height="76" style="display: block; margin: 0 auto;">
                <h2 style="color: #ffffff; margin: 10px 0 0; font-size: 24px;">Bulacan Polytechnic College Registrar</h2>
            </td>
        </tr>
        <!-- Body -->
        <tr>
            <td style="padding: 30px; text-align: center;">
                <h3 style="color: #2e7d32; margin: 0 0 15px; font-size: 20px;">Welcome, ' . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . '!</h3>
                <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px;">
                    An account has been created for you in the BPC Document Request System. Please verify your email address to activate your account and start requesting documents. This link will expire in 24 hours.
                </p>
                <a href="http://localhost/capstone-admin/verify-email.php?token=' . urlencode($verify_token) . '" style="display: inline-block; padding: 12px 24px; background-color: #2e7d32; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; border-radius: 5px; margin: 20px 0;">Verify Your Email</a>
                <p style="color: #666666; font-size: 14px; margin: 20px 0 0;">
                    If the button above doesn’t work, copy and paste this link into your browser:<br>
                    <a href="http://localhost/capstone-admin/verify-email.php?token=' . urlencode($verify_token) . '" style="color: #2e7d32; text-decoration: underline;">http://localhost/capstone-admin/verify-email.php?token=' . urlencode($verify_token) . '</a>
                </p>
            </td>
        </tr>
        <!-- Footer -->
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #f4f4f4; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
                <p style="color: #666666; font-size: 12px; margin: 0;">
                    © ' . date('Y') . ' Bulacan Polytechnic College. All rights reserved.<br>
                    If you did not request this account, please ignore this email or contact support.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
';
                $mail->AltBody = "Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ",\n\nAn account has been created for you in the BPC Document Request System. Please verify your email address by clicking the link below (expires in 24 hours):\n\nhttp://localhost/capstone-admin/verify-email.php?token=" . urlencode($verify_token) . "\n\nIf you did not request this account, please ignore this email or contact support.\n\nBest regards,\nBulacan Polytechnic College Registrar";
                $mail->send();
            } catch (Exception $e) {
                error_log("Email sending failed for user ID $user_id: " . $e->getMessage());
                redirect('students.php?' . http_build_query($_GET), 'User added, but email verification failed. Please ask the user to contact support.', 'warning');
            }

            redirect('students.php?' . http_build_query($_GET), 'User added successfully. A verification email has been sent to ' . htmlspecialchars($email) . '.', 'success');
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
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $course_id = (int)validate($_POST['course_id']);
        $section_id = (int)validate($_POST['section_id']);
        $year_id = (int)validate($_POST['year_id']);
        $year_level = validate($_POST['year_level']);
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);
        $terms = isset($_POST['terms']) ? 1 : 0;

        // Validate student ID format (must start with "MA")
        if (!preg_match('/^MA[0-9]+$/', $studentid)) {
            redirect('students.php?' . http_build_query($_GET), 'Student ID must start with "MA" followed by numbers (e.g., MA1231232).', 'danger');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid email format.', 'danger');
        }

        // Validate password if provided
        if (!empty($password) && (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password))) {
            redirect('students.php?' . http_build_query($_GET), 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        // Check if student ID already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE studentid = ? AND id != ?");
        $stmt->bind_param("si", $studentid, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Student ID already exists.', 'danger');
        }
        $stmt->close();

        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('students.php?' . http_build_query($_GET), 'Email already exists.', 'danger');
        }
        $stmt->close();

        // Validate year level
        if (!in_array($year_level, ['1st Year', '2nd Year', '3rd Year', '4th Year'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid year level.', 'danger');
        }

        // Validate role
        if (!in_array($role, ['student', 'alumni'])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid role.', 'danger');
        }

        // Validate status
        if (!in_array($is_ban, [0, 1])) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid status.', 'danger');
        }

        // Validate course
        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid course selected.', 'danger');
        }
        $stmt->close();

        // Validate section
        $stmt = $conn->prepare("SELECT id FROM sections WHERE id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid section selected.', 'danger');
        }
        $stmt->close();

        // Validate school year
        $stmt = $conn->prepare("SELECT id FROM school_years WHERE id = ?");
        $stmt->bind_param("i", $year_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows == 0) {
            redirect('students.php?' . http_build_query($_GET), 'Invalid school year selected.', 'danger');
        }
        $stmt->close();

        // Update user (excluding profile since there's no upload feature yet)
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE users 
                SET studentid = ?, firstname = ?, middlename = ?, lastname = ?, email = ?, password = ?, course_id = ?, section_id = ?, year_id = ?, year_level = ?, role = ?, terms = ?, is_ban = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssiisssiii", $studentid, $firstname, $middlename, $lastname, $email, $hashed_password, $course_id, $section_id, $year_id, $year_level, $role, $terms, $is_ban, $id);
        } else {
            $stmt = $conn->prepare("
                UPDATE users 
                SET studentid = ?, firstname = ?, middlename = ?, lastname = ?, email = ?, course_id = ?, section_id = ?, year_id = ?, year_level = ?, role = ?, terms = ?, is_ban = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("sssssiisssiii", $studentid, $firstname, $middlename, $lastname, $email, $course_id, $section_id, $year_id, $year_level, $role, $terms, $is_ban, $id);
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