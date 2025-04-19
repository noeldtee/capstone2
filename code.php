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
    $required = ['firstname', 'middlename', 'lastname', 'studentid', 'year_id', 'course_id', 'year_level', 'section_id', 'number', 'birthdate', 'gender', 'email', 'password', 'confirm_password', 'terms'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            // Store form data in session, excluding passwords
            $form_data = $data;
            unset($form_data['password']);
            unset($form_data['confirm_password']);
            $_SESSION['form_data'] = $form_data;
            redirect('register.php', 'All fields are mandatory.', 'danger');
            exit;
        }
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
        redirect('register.php', trim($error_message), 'danger');
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

    // Determine role based on school year's status
    $stmt = $conn->prepare("SELECT status FROM school_years WHERE id = ?");
    $stmt->bind_param("i", $data['year_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Invalid school year selected.', 'danger');
        exit;
    }
    $school_year = $result->fetch_assoc();
    $school_year_status = $school_year['status'];
    $stmt->close();

    // Set role based on school year status
    if ($school_year_status === 'Current') {
        $role = 'student';
    } elseif ($school_year_status === 'Past') {
        $role = 'alumni';
    } else {
        $role = 'inactive';
    }

    // Prepare data for insertion
    $studentid = $data['studentid'];
    $firstname = $data['firstname'];
    $middlename = $data['middlename'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $number = $data['number'];
    $gender = $data['gender'];
    $birthdate = $data['birthdate'];
    $year_level = $data['year_level'];
    $terms = $data['terms'] ? 1 : 0;
    $verify_status = 0;

    $stmt = $conn->prepare("INSERT INTO users (studentid, firstname, middlename, lastname, email, number, password, profile, gender, birthdate, course_id, section_id, year_id, year_level, role, terms, verify_status, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssiiisiiis", $studentid, $firstname, $middlename, $lastname, $email, $number, $hashedPassword, $profilePath, $gender, $birthdate, $data['course_id'], $data['section_id'], $data['year_id'], $year_level, $role, $terms, $verify_status, $verify_token);

    if ($stmt->execute()) {
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
            $mail->setFrom('bpcregistrar75@gmail.com', $firstname);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Email Verification from Bulacan Polytechnic College Registrar';
            $mail->Body = "
                <h2>Email Verification</h2>
                <p>Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ", Please click the link below to verify your email address.</p>
                <br><br>
                <a href='http://localhost/capstone-admin/verify-email.php?token=" . urlencode($verify_token) . "'>Verify Email</a>
            ";
            $mail->send();
        } catch (Exception $e) {
            redirect('register.php', 'Registration successful, but email verification failed: ' . $e->getMessage(), 'warning');
            exit;
        }
        redirect('register.php', 'Registration successful! Please verify your email address.', 'success');
    } else {
        $form_data = $data;
        unset($form_data['password']);
        unset($form_data['confirm_password']);
        $_SESSION['form_data'] = $form_data;
        redirect('register.php', 'Registration failed. Please try again.', 'danger');
    }

    $stmt->close();
} else {
    redirect('register.php', 'Invalid request.', 'danger');
}

$conn->close();
?>