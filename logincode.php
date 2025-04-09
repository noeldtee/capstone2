<?php
require 'config/function.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['loginBtn'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirect('index.php', 'Invalid CSRF token.', 'danger');
        exit();
    }

    // Rate limiting: Allow only 5 login attempts per 2 minutes
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempt_time'] = time();
    }

    if ((time() - $_SESSION['login_attempt_time']) > 120) { // 2 minutes
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempt_time'] = time();
    }

    if ($_SESSION['login_attempts'] >= 5) {
        redirect('index.php', 'Too many login attempts. Please try again after 5 minutes.', 'danger');
        exit();
    }

    $emailInput = validate($_POST['email']);
    $passwordInput = validate($_POST['password']);

    $email = filter_var($emailInput, FILTER_SANITIZE_EMAIL);
    $password = filter_var($passwordInput, FILTER_SANITIZE_STRING);

    if (empty($email) || empty($password)) {
        $_SESSION['login_attempts']++;
        redirect('index.php', 'All fields are mandatory.', 'danger');
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['login_attempts']++;
        redirect('index.php', 'Invalid email format.', 'danger');
        exit();
    }

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        // Verify password (assuming hashed in database)
        if (password_verify($password, $row['password'])) {
            // Check email verification status
            if ($row['verify_status'] == 0) {
                redirect('index.php', 'Please verify your email before logging in.', 'warning');
                exit();
            }

            // Check if user is banned
            if ($row['is_ban'] == 1) {
                redirect('index.php', 'Your account has been banned. Please contact admin.', 'danger');
                exit();
            }

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Set session variables
            $_SESSION['auth'] = true;
            $_SESSION['user_id'] = $row['id']; // Added: Set user_id
            $_SESSION['role'] = $row['role'];
            $_SESSION['loggedInUser'] = [
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email']
            ];

            // Handle "Remember Me" functionality
            if (isset($_POST['remember_me'])) {
                $remember_token = bin2hex(random_bytes(16));
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

                $stmt = $conn->prepare("UPDATE users SET remember_token = ?, remember_expires_at = ? WHERE email = ?");
                $stmt->bind_param("sss", $remember_token, $expires_at, $email);
                $stmt->execute();

                setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            } else {
                $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expires_at = NULL WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                setcookie('remember_token', '', time() - 3600, '/', '', false, true);
                setcookie('remember_email', '', time() - 3600, '/', '', false, true);
            }

            // Reset login attempts on successful login
            $_SESSION['login_attempts'] = 0;

            // Redirect based on role (updated paths)
            switch ($row['role']) {
                case 'admin':
                    redirect('admin/dashboard.php', 'Welcome Admin', 'success');
                    break;
                case 'staff':
                    redirect('staff/dashboard.php', 'Welcome Staff', 'success');
                    break;
                case 'cashier':
                    redirect('cashier/dashboard.php', 'Welcome Cashier', 'success');
                    break;
                case 'student':
                case 'alumni':
                    redirect('users/dashboard.php', 'Welcome to the Student Dashboard', 'success');
                    break;
                default:
                    redirect('index.php', 'Invalid Role', 'danger');
                    exit();
            }
        } else {
            error_log("Failed login attempt for email: $email at " . date('Y-m-d H:i:s'));
            $_SESSION['login_attempts']++;
            redirect('index.php', 'Invalid email or password. Please try again.', 'danger');
            exit();
        }
    } else {
        error_log("Failed login attempt for email: $email at " . date('Y-m-d H:i:s'));
        $_SESSION['login_attempts']++;
        redirect('index.php', 'Invalid email or password. Please try again.', 'danger');
        exit();
    }
} else {
    redirect('index.php', 'Please log in to continue.', 'warning');
    exit();
}

$conn->close();
?>