<?php
require 'config/function.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Start the session to store form data
session_start();

if (isset($_POST['register_btn'])) {
    $data = validate($_POST);
    $profile = $_FILES['profile'] ?? null;

    // Initialize array to track invalid fields
    $data['invalid_fields'] = [];

    // Validate required fields
    $required = ['firstname', 'middlename', 'lastname', 'studentid', 'year_id', 'course_id', 'year_level', 'section_id', 'number', 'birthdate', 'gender', 'email', 'password', 'confirm_password', 'terms', 'role'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $form_data = $data;
            unset($form_data['password']);
            unset($form_data['confirm_password']);
            $_SESSION['form_data'] = $form_data;
            redirect('register.php', 'All fields are mandatory.', 'danger');
            exit;
        }
    }

    // Validate role
    if (!in_array($data['role'], ['student', 'alumni'])) {
        $data['invalid_fields'][] = 'role';
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Invalid role selected. Please choose Student or Alumni.', 'danger');
        exit;
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $data['invalid_fields'][] = 'email';
    }

    // Validate password
    $password = $data['password'];
    $confirm_password = $data['confirm_password'];
    if ($password !== $confirm_password) {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Passwords do not match.', 'danger');
        exit;
    }
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Password must be at least 8 characters long and include at least one number and one special character (e.g., !@#$%^&*).', 'danger');
        exit;
    }

    // Validate phone number
    if (!preg_match('/^((\+63[0-9]{10})|(09[0-9]{9}))$/', $data['number'])) {
        $data['invalid_fields'][] = 'number';
    }

    // Validate birthdate (14–40 years)
    $birthdate = new DateTime($data['birthdate']);
    $now = new DateTime();
    $age = $now->diff($birthdate)->y;
    if ($age < 14 || $age > 40) {
        $data['invalid_fields'][] = 'birthdate';
    }

    // Validate student ID format
    if (!preg_match('/^MA[0-9]+$/', $data['studentid'])) {
        $data['invalid_fields'][] = 'studentid';
    }

    // If there are validation errors so far, redirect
    if (!empty($data['invalid_fields'])) {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        $error_message = '';
        if (in_array('email', $data['invalid_fields'])) {
            $error_message .= 'Invalid email format. ';
        }
        if (in_array('number', $data['invalid_fields'])) {
            $error_message .= 'Phone number must start with +63 followed by 10 digits (e.g., +639123456789) or start with 09 followed by 9 digits (e.g., 09123456789). ';
        }
        if (in_array('birthdate', $data['invalid_fields'])) {
            $error_message .= 'Age must be between 14 and 40 years. ';
        }
        if (in_array('studentid', $data['invalid_fields'])) {
            $error_message .= 'Student ID must start with "MA" followed by numbers (e.g., MA1231232). ';
        }
        if (in_array('role', $data['invalid_fields'])) {
            $error_message .= 'Invalid role selected. ';
        }
        redirect('register.php', trim($error_message), 'danger');
        exit;
    }

    // Validate course_id
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $data['course_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        $data['invalid_fields'][] = 'course_id';
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Invalid course selected.', 'danger');
        exit;
    }
    $stmt->close();

    // Validate section_id and school year status
    $stmt = $conn->prepare("
        SELECT s.id, sy.status 
        FROM sections s 
        JOIN school_years sy ON s.school_year_id = sy.id 
        WHERE s.id = ? 
        AND s.school_year_id = ? 
        AND s.course_id = ? 
        AND s.year_level = ?
    ");
    $stmt->bind_param("iiis", $data['section_id'], $data['year_id'], $data['course_id'], $data['year_level']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $data['invalid_fields'][] = 'section_id';
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Invalid section selected.', 'danger');
        exit;
    }
    $school_year = $result->fetch_assoc();
    $year_status = $school_year['status'];
    $stmt->close();

    // Validate role against school year status
    $expected_status = $data['role'] === 'student' ? 'Current' : 'Past';
    if ($year_status !== $expected_status) {
        $data['invalid_fields'][] = 'year_id';
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Selected school year does not match the chosen role. Students must select a Current school year, and Alumni must select a Past school year.', 'danger');
        exit;
    }

    // Check if studentid, email, or number already exists
    $errors = [];

    $stmt = $conn->prepare("SELECT studentid FROM users WHERE studentid = ? LIMIT 1");
    $stmt->bind_param("s", $data['studentid']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Student ID is already taken.';
        $data['invalid_fields'][] = 'studentid';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Email is already taken.';
        $data['invalid_fields'][] = 'email';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT number FROM users WHERE number = ? LIMIT 1");
    $stmt->bind_param("s", $data['number']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Phone Number is already taken.';
        $data['invalid_fields'][] = 'number';
    }
    $stmt->close();

    if (!empty($errors)) {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', implode(' ', $errors), 'danger');
        exit;
    }

    // Handle profile upload or set default
    $profilePath = 'assets/images/default_profile.png';
    if ($profile && $profile['error'] == 0) {
        $fileSize = $profile['size'];
        $fileType = mime_content_type($profile['tmp_name']);
        if ($fileSize > 2000000) {
            $form_data = $data;
            unset($form_data['password']);
            unset($form_data['confirm_password']);
            $_SESSION['form_data'] = $form_data;
            redirect('register.php', 'File size must be less than 2MB.', 'danger');
            exit;
        }
        if (!str_starts_with($fileType, 'image/')) {
            $form_data = $data;
            unset($form_data['password']);
            unset($form_data['confirm_password']);
            $_SESSION['form_data'] = $form_data;
            redirect('register.php', 'Only image files are allowed.', 'danger');
            exit;
        }
        $fileName = time() . "_" . basename($profile['name']);
        $target = "assets/images/" . $fileName;
        if (!file_exists('assets/images')) {
            mkdir('assets/images', 0777, true);
        }
        if (move_uploaded_file($profile['tmp_name'], $target)) {
            $profilePath = $target;
        } else {
            $form_data = $data;
            unset($form_data['password']);
            unset($form_data['confirm_password']);
            $_SESSION['form_data'] = $form_data;
            redirect('register.php', 'Failed to upload profile picture.', 'danger');
            exit;
        }
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Generate verification token
    $verify_token = bin2hex(random_bytes(16));

    // Prepare data for insertion
    $studentid = $data['studentid'];
    $firstname = $data['firstname'];
    $role = $data['role'];
    $middlename = $data['middlename'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $number = $data['number'];
    $gender = $data['gender'];
    $birthdate = $data['birthdate'];
    $year_level = $data['year_level'];
    $terms = $data['terms'] ? 1 : 0;
    $verify_status = 0;


    // Log before insertion
    error_log("Inserting user: studentid=$studentid, email=$email, role=$role, section_id={$data['section_id']}, year_id={$data['year_id']}");

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO users (studentid, firstname, middlename, lastname, email, number, password, profile, gender, birthdate, course_id, section_id, year_id, year_level, role, terms, verify_status, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        redirect('register.php', 'Registration failed: Unable to prepare statement.', 'danger');
        exit;
    }

    $stmt->bind_param("ssssssssssiiisiiis", $studentid, $firstname, $middlename, $lastname, $email, $number, $hashedPassword, $profilePath, $gender, $birthdate, $data['course_id'], $data['section_id'], $data['year_id'], $year_level, $role, $terms, $verify_status, $verify_token);
    if ($stmt->execute()) {
        $inserted_id = $stmt->insert_id;
        error_log("User inserted successfully: ID=$inserted_id, studentid=$studentid, role=$role");

        // Clear form data from session on successful registration
        unset($_SESSION['form_data']);
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
                    Thank you for registering with the BPC Document Request System. Please verify your email address to activate your account and start requesting documents.
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
                    If you did not register for this account, please ignore this email.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
';
            $mail->AltBody = "Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ",\n\nThank you for registering with the BPC Document Request System. Please verify your email address by clicking the link below:\n\nhttp://localhost/capstone-admin/verify-email.php?token=" . urlencode($verify_token) . "\n\nIf you did not register, please ignore this email.\n\nBest regards,\nBulacan Polytechnic College Registrar";
            $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            redirect('register.php', 'Registration successful, but email verification failed. Please contact support.', 'warning');
            exit;
        }
        redirect('register.php', 'Registration successful! Please verify your email address.', 'success');
    } else {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        error_log("Database insertion failed: " . $stmt->error);
        redirect('register.php', 'Registration failed: ' . $stmt->error, 'danger');
    }

    $stmt->close();
} else {
    redirect('register.php', 'Invalid request.', 'danger');
}

$conn->close();
?>