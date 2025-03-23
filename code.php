<?php
require 'config/function.php'; // Use $conn from dbcon.php via function.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (isset($_POST['register_btn'])) {
    // Sanitize and validate inputs using function.php's validate()
    $data = validate($_POST);
    $profile = $_FILES['profile'] ?? null;

    // Validate required fields
    $required = ['firstname', 'lastname', 'studentid', 'year', 'course', 'section', 'number', 'birthdate', 'gender', 'email', 'password', 'confirm_password', 'terms'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            redirect('register.php', 'All fields are mandatory.', 'danger');
            exit();
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        redirect('register.php', 'Invalid email format.', 'danger');
        exit();
    }

    // Validate password
    $password = $data['password'];
    $confirm_password = $data['confirm_password'];
    if ($password !== $confirm_password) {
        redirect('register.php', 'Passwords do not match.', 'danger');
        exit();
    }
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        redirect('register.php', 'Password must be at least 8 characters long and include at least one number and one special character (e.g., !@#$%^&*).', 'danger');
        exit();
    }

    // Validate phone number (Philippine format: +63 followed by 10 digits)
    if (!preg_match('/^\+63[0-9]{10}$/', $data['number'])) {
        redirect('register.php', 'Phone number must start with +63 followed by a 10-digit number (e.g., +639123456789).', 'danger');
        exit();
    }

    // Validate birthdate (18–60 years)
    $birthdate = new DateTime($data['birthdate']);
    $now = new DateTime();
    $age = $now->diff($birthdate)->y;
    if ($age < 14 || $age > 60) {
        redirect('register.php', 'Age must be between 14 and 60 years.', 'danger');
        exit();
    }

    // Check if studentid already exists (using prepared statement)
    $stmt = $conn->prepare("SELECT * FROM users WHERE studentid = ? LIMIT 1");
    $stmt->bind_param("s", $data['studentid']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) > 0) {
        redirect('register.php', 'Student ID already exists.', 'danger');
        exit();
    }

    // Check if email already exists (using prepared statement)
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) > 0) {
        redirect('register.php', 'Email already exists.', 'danger');
        exit();
    }

    // Handle profile upload or set default
    $profilePath = 'assets/images/default_profile.png'; // Default profile picture
    if ($profile && $profile['error'] == 0) {
        $fileSize = $profile['size'];
        $fileType = mime_content_type($profile['tmp_name']);
        if ($fileSize > 2000000) { // 2MB
            redirect('register.php', 'File size must be less than 2MB.', 'danger');
            exit();
        }
        if (!str_starts_with($fileType, 'image/')) {
            redirect('register.php', 'Only image files are allowed.', 'danger');
            exit();
        }
        $fileName = time() . "_" . basename($profile['name']);
        $target = "uploads/" . $fileName;
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }
        if (move_uploaded_file($profile['tmp_name'], $target)) {
            $profilePath = $target; // Override default if upload succeeds
        } else {
            redirect('register.php', 'Failed to upload profile picture.', 'danger');
            exit();
        }
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Generate secure verification token
    $verify_token = bin2hex(random_bytes(16)); // 32-character hex token

    // Determine role based on school year
    $currentYear = date('Y'); // e.g., 2025
    $lastYear = $currentYear - 1; // e.g., 2024
    $currentSchoolYear = "$lastYear-$currentYear"; // e.g., "2024-2025"
    $previousSchoolYear = ($lastYear - 1) . "-$lastYear"; // e.g., "2023-2024"

    $year = $data['year']; // School year input (e.g., "2024-2025")
    if ($year === $currentSchoolYear || $year === $previousSchoolYear) {
        $role = 'student'; // Current or last year
    } elseif (preg_match('/^\d{4}-\d{4}$/', $year) && substr($year, 0, 4) < $lastYear - 1) {
        $role = 'alumni'; // Past years (e.g., "2022-2023" or earlier)
    } else {
        $role = 'inactive'; // Invalid or future years
    }

    // Prepare data for insertion
    $studentid = $data['studentid'];
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $number = $data['number'];
    $gender = $data['gender'];
    $birthdate = $data['birthdate'];
    $course = $data['course'];
    $section = $data['section'];
    $year_level = $data['year_level'];
    $terms = $data['terms'] ? 1 : 0;
    $verify_status = 0; // Unverified by default

    // Insert user data using prepared statement
    $stmt = $conn->prepare("INSERT INTO users (studentid, firstname, lastname, email, number, password, profile, gender, birthdate, course, section, year, year_level, role, terms, verify_status, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssssssis", $studentid, $firstname, $lastname, $email, $number, $hashedPassword, $profilePath, $gender, $birthdate, $course, $section, $year, $year_level, $role, $terms, $verify_status, $verify_token);

    if ($stmt->execute()) {
        try {
            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            // $mail->SMTPDebug = 2; // Uncomment for debugging
            $mail->isSMTP();
            $mail->SMTPAuth = true;

            $mail->Host = 'smtp.gmail.com';
            $mail->Username = 'bpcregistrar75@gmail.com';
            $mail->Password = 'nkei hmzy qpwn wzch'; // Use app-specific password

            $mail->SMTPSecure = "tls";
            $mail->Port = 587;

            $mail->setFrom('bpcregistrar75@gmail.com', $firstname);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Email Verification from Bulacan Polytechnic College Registrar';

            $email_template = "
                <h2>Email Verification</h2>
                <p>Hi " . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ", Please click the link below to verify your email address.</p>
                <br><br>
                <a href='http://localhost/capstone-admin/verify-email.php?token=" . urlencode($verify_token) . "'>Verify Email</a>
            ";

            $mail->Body = $email_template;
            $mail->send();
        } catch (Exception $e) {
            redirect('register.php', 'Registration successful, but email verification failed: ' . $e->getMessage(), 'warning');
            exit();
        }

        redirect('register.php', 'Registration successful! Please verify your email address.', 'success');
    } else {
        redirect('register.php', 'Registration failed. Please try again.', 'danger');
    }

    $stmt->close();
} else {
    redirect('register.php', 'Invalid request.', 'danger');
}

// Close database connection
$conn->close();
?>