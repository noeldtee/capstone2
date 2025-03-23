<?php
require 'config/function.php';

if (isset($_POST['loginBtn'])) {
    $emailInput = validate($_POST['email']);
    $passwordInput = validate($_POST['password']);

    $email = filter_var($emailInput, FILTER_SANITIZE_EMAIL);
    $password = filter_var($passwordInput, FILTER_SANITIZE_STRING);

    if (empty($email) || empty($password)) {
        redirect('index.php', 'All fields are mandatory.', 'danger');
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            $_SESSION['role'] = $row['role'];
            $_SESSION['loggedInUser'] = [
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email']
            ];

            // Handle "Remember Me" functionality
            if (isset($_POST['remember_me'])) {
                // Set a secure cookie for 30 days (email only for convenience)
                setcookie('remember_email', $email, time() + (30 * 24 * 60 * 60), '/', '', true, true); // Secure and HttpOnly
            }

            // Redirect based on role
            switch ($row['role']) {
                case 'admin':
                    redirect('admin/index.php', 'Welcome Admin', 'success');
                    break;
                case 'staff':
                    redirect('staff/index.php', 'Welcome Staff', 'success');
                    break;
                case 'cashier':
                    redirect('cashier/index.php', 'Welcome Cashier', 'success');
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
            redirect('index.php', 'Invalid email or password. Please try again.', 'danger');
            exit();
        }
    } else {
        redirect('index.php', 'Invalid email or password. Please try again.', 'danger');
        exit();
    }
} else {
    redirect('index.php', 'Please log in to continue.', 'warning');
    exit();
}

// Close database connection
$conn->close();