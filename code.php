<?php
require 'config/function.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (isset($_POST['register_btn'])) {
    $data = validate($_POST);
    $profile = $_FILES['profile'] ?? null;

    // Validate required fields
    $required = ['firstname', 'lastname', 'studentid', 'year_id', 'course_id', 'year_level', 'section_id', 'number', 'birthdate', 'gender', 'email', 'password', 'confirm_password', 'terms'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            redirect('register.php', 'All fields are mandatory.', 'danger');
            exit;
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        redirect('register.php', 'Invalid email format.', 'danger');
        exit;
    }

    // Validate password
    $password = $data['password'];
    $confirm_password = $data['confirm_password'];
    if ($password !== $confirm_password) {
        redirect('register.php', 'Passwords do not match.', 'danger');
        exit;
    }
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        redirect('register.php', 'Password must be at least 8 characters long and include at least one number and one special character (e.g., !@#$%^&*).', 'danger');
        exit;
    }

    // Validate phone number
    if (!preg_match('/^((\+63[0-9]{10})|(09[0-9]{9}))$/', $data['number'])) {
        redirect('register.php', 'Phone number must start with +63 followed by 10 digits (e.g., +639123456789) or start with 09 followed by 9 digits (e.g., 09123456789).', 'danger');
        exit;
    }

    // Validate birthdate (18–60 years)
    $birthdate = new DateTime($data['birthdate']);
    $now = new DateTime();
    $age = $now->diff($birthdate)->y;
    if ($age < 18 || $age > 60) {
        redirect('register.php', 'Age must be between 18 and 60 years.', 'danger');
        exit;
    }

    // Fetch school year, course, and section details (for validation only)
    $year_id = (int)$data['year_id'];
    $course_id = (int)$data['course_id'];
    $section_id = (int)$data['section_id'];

    $stmt = $conn->prepare("SELECT year FROM school_years WHERE id = ?");
    $stmt->bind_param("i", $year_id);
    $stmt->execute();
    $year_result = $stmt->get_result()->fetch_assoc();
    $year = $year_result ? $year_result['year'] : null;
    if (!$year) {
        redirect('register.php', 'Invalid school year selected.', 'danger');
        exit;
    }

    $stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course_result = $stmt->get_result()->fetch_assoc();
    $course = $course_result ? $course_result['name'] : null;
    if (!$course) {
        redirect('register.php', 'Invalid course selected.', 'danger');
        exit;
    }

    $stmt = $conn->prepare("SELECT section FROM sections WHERE id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $section_result = $stmt->get_result()->fetch_assoc();
    $section = $section_result ? $section_result['section'] : null;
    if (!$section) {
        redirect('register.php', 'Invalid section selected.', 'danger');
        exit;
    }

    // Check if studentid already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE studentid = ? LIMIT 1");
    $stmt->bind_param("s", $data['studentid']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        redirect('register.php', 'Student ID already exists.', 'danger');
        exit;
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        redirect('register.php', 'Email already exists.', 'danger');
        exit;
    }

    // Handle profile upload or set default
    $profilePath = 'assets/images/default_profile.png';
    if ($profile && $profile['error'] == 0) {
        $fileSize = $profile['size'];
        $fileType = mime_content_type($profile['tmp_name']);
        if ($fileSize > 2000000) {
            redirect('register.php', 'File size must be less than 2MB.', 'danger');
            exit;
        }
        if (!str_starts_with($fileType, 'image/')) {
            redirect('register.php', 'Only image files are allowed.', 'danger');
            exit;
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
            exit;
        }
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Generate verification token
    $verify_token = bin2hex(random_bytes(16));

    // Determine role based on school year
    $currentYear = date('Y');
    $lastYear = $currentYear - 1;
    $currentSchoolYear = "$lastYear-$currentYear";
    $previousSchoolYear = ($lastYear - 1) . "-$lastYear";

    // Fix role determination: Extract startYear from $year
    list($startYear, $endYear) = explode('-', $year);
    $startYear = (int)$startYear;
    if ($year === $currentSchoolYear || $year === $previousSchoolYear) {
        $role = 'student';
    } elseif ($startYear < $lastYear - 1) {
        $role = 'alumni';
    } else {
        $role = 'inactive';
    }

    // Prepare data for insertion
    $studentid = $data['studentid'];
    $firstname = $data['firstname'];
    $lastname = $data['lastname'];
    $email = $data['email'];
    $number = $data['number'];
    $gender = $data['gender'];
    $birthdate = $data['birthdate'];
    $year_level = $data['year_level'];
    $terms = $data['terms'] ? 1 : 0;
    $verify_status = 0;

    // Updated INSERT query to match the table structure
    $stmt = $conn->prepare("INSERT INTO users (studentid, firstname, lastname, email, number, password, profile, gender, birthdate, course_id, section_id, year_id, year_level, role, terms, verify_status, verify_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssiiisssis", $studentid, $firstname, $lastname, $email, $number, $hashedPassword, $profilePath, $gender, $birthdate, $course_id, $section_id, $year_id, $year_level, $role, $terms, $verify_status, $verify_token);

    if ($stmt->execute()) {
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
        redirect('register.php', 'Registration failed. Please try again.', 'danger');
    }

    $stmt->close();
} else {
    redirect('register.php', 'Invalid request.', 'danger');
}

$conn->close();
?>