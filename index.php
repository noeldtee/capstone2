<?php
$page_title = "Login Form";
include('includes/header.php');

redirectIfLoggedIn(); // Redirect if logged in

// Check for "Remember Me" token and auto-login
if (!isset($_SESSION['auth']) && isset($_COOKIE['remember_token'])) {
    $remember_token = validate($_COOKIE['remember_token']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->bind_param("s", $remember_token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        // Check if token has expired
        $expires_at = new DateTime($row['remember_expires_at']);
        $now = new DateTime();
        if ($now > $expires_at) {
            $stmt = $conn->prepare("UPDATE users SET remember_token = NULL, remember_expires_at = NULL WHERE email = ?");
            $stmt->bind_param("s", $row['email']);
            $stmt->execute();
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        } else {
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            $_SESSION['user_id'] = $row['id']; // Added: Set user_id
            $_SESSION['role'] = $row['role'];
            $_SESSION['loggedInUser'] = [
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email']
            ];

            // Debug: Confirm session after Remember Me
            echo "<pre>After Remember Me - Session Auth: " . var_export($_SESSION['auth'], true) . "\n";
            echo "User ID: " . $_SESSION['user_id'] . "\n"; // Debug
            echo "Role: " . $_SESSION['role'] . "</pre>";

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
        }
    }
    $conn->close();
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for "Remember Me" cookie to pre-fill email
$remembered_email = '';
$remembered_checked = '';
if (isset($_COOKIE['remember_token'])) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE remember_token = ? LIMIT 1");
    $stmt->bind_param("s", $_COOKIE['remember_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && mysqli_num_rows($result) == 1) {
        $row = $result->fetch_assoc();
        $remembered_email = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
        $remembered_checked = 'checked';
    }
    $conn->close();
}
?>

<div class="d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-sm p-4" style="width: 100%; max-width: 500px; background-color: white; border-radius: 10px;">
        <div class="text-center mb-4">
            <img src="assets/images/logo.png" alt="Logo" width="80" height="76">
            <h5 class="mt-3" style="color: #2e7d32;">BPC Document Request System</h5>
        </div>

        <?php alertMessage(); ?>

        <form action="logincode.php" method="POST">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-floating mb-3">
                <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" value="<?php echo $remembered_email; ?>" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                <label for="email">Email Address</label>
                <div id="email-error" class="text-danger mt-1" style="font-size: 0.875rem;"></div>
            </div>
            <div class="form-floating mb-3">
                <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                <label for="password">Password</label>
                <div id="password-error" class="text-danger mt-1" style="font-size: 0.875rem;"></div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" <?php echo $remembered_checked; ?>>
                <label class="form-check-label" for="remember_me">Remember Me</label>
            </div>
            <div class="form-group mb-3">
                <button type="submit" name="loginBtn" class="btn btn-success w-100" style="background-color: #2e7d32; border: none;">Login</button>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <a href="resend_verification.php" class="text-decoration-none" style="color: #2e7d32;">Resend Verification Email</a>
                <a href="password-reset.php" class="text-decoration-none" style="color: #2e7d32;">Forgot your password?</a>
            </div>

            <hr>
            <div class="text-center mt-3">
                Don't have an account?
                <a href="register.php" class="text-decoration-none" style="color: #2e7d32;">Register Now</a>
            </div>
        </form>
    </div>
</div>

<script>
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const emailError = document.getElementById('email-error');
    const passwordError = document.getElementById('password-error');
    const form = document.querySelector('form');

    // Function to validate email in real-time
    function validateEmail() {
        const emailPattern = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
        if (!emailPattern.test(emailInput.value)) {
            emailInput.setCustomValidity('Please enter a valid email address.');
            emailError.textContent = 'Please enter a valid email address.';
        } else {
            emailInput.setCustomValidity('');
            emailError.textContent = '';
        }
    }

    // Function to validate password (not empty)
    function validatePassword() {
        if (passwordInput.value.trim() === '') {
            passwordInput.setCustomValidity('Password cannot be empty.');
            passwordError.textContent = 'Password cannot be empty.';
        } else {
            passwordInput.setCustomValidity('');
            passwordError.textContent = '';
        }
    }

    // Validate on input change
    emailInput.addEventListener('input', validateEmail);
    passwordInput.addEventListener('input', validatePassword);

    // Validate on form submission
    form.addEventListener('submit', function(event) {
        validateEmail();
        validatePassword();
        if (!form.checkValidity()) {
            event.preventDefault();
            emailInput.reportValidity();
            passwordInput.reportValidity();
        }
    });
</script>

<?php include('includes/footer.php'); ?>