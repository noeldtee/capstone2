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

    // Validate password match
    if ($data['password'] !== $data['confirm_password']) {
        redirect('register.php', 'Passwords do not match.', 'danger');
        exit();
    }

    // Validate phone number (10-11 digits)
    if (!preg_match('/^[0-9]{10,11}$/', $data['number'])) {
        redirect('register.php', 'Invalid phone number. Enter a 10-11 digit number.', 'danger');
        exit();
    }

    // Validate birthdate (e.g., 18–100 years)
    $birthdate = new DateTime($data['birthdate']);
    $now = new DateTime();
    $age = $now->diff($birthdate)->y;
    if ($age < 18 || $age > 100) {
        redirect('register.php', 'Invalid birthdate. Age must be between 18 and 100.', 'danger');
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

    // Handle profile upload
    $profilePath = NULL;
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
            $profilePath = $target;
        } else {
            redirect('register.php', 'Failed to upload profile picture.', 'danger');
            exit();
        }
    }

    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // Generate secure verification token
    $verify_token = bin2hex(random_bytes(16)); // 32-character hex token

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
    $year = $data['year'];
    $year_level = $data['year_level'];
    $terms = $data['terms'] ? 1 : 0;
    $role = 'student'; // Default role for new registrations

    // Insert user data using prepared statement
    $stmt = $conn->prepare("INSERT INTO users (studentid, firstname, lastname, email, number, password, profile, gender, birthdate, course, section, year, year_level, role, terms, verify_status, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $verify_status = 0; // Unverified by default
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
            <p>Hi {$firstname}, Please click the link below to verify your email address.</p>
            <br><br>
            <a href='http://localhost/capstone-admin/verify-email.php?token={$verify_token}'>Verify Email</a>
            ";

            $mail->Body = htmlspecialchars($email_template, ENT_QUOTES, 'UTF-8');
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