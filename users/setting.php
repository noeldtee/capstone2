<?php
// Include header
$page_title = "Settings";
include('includes/header.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['alert'] = ['message' => 'Please log in to manage your settings.', 'type' => 'danger'];
    header('Location: index.php');
    exit;
}

// Fetch user data from the database
$user_id = $_SESSION['user_id'];
$user_data = null;

$stmt = $conn->prepare("
    SELECT firstname, lastname, email, number, studentid, year_level, course_id, section_id, birthdate, password
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
} else {
    $_SESSION['alert'] = ['message' => 'User not found.', 'type' => 'danger'];
    header('Location: index.php');
    exit;
}
$stmt->close();

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['alert'] = ['message' => 'All fields are required.', 'type' => 'danger'];
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['alert'] = ['message' => 'New password and confirmation do not match.', 'type' => 'danger'];
    } elseif (strlen($new_password) < 8) {
        $_SESSION['alert'] = ['message' => 'New password must be at least 8 characters long.', 'type' => 'danger'];
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $_SESSION['alert'] = ['message' => 'Current password is incorrect.', 'type' => 'danger'];
    } else {
        // Hash the new password and update the database
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            $_SESSION['alert'] = ['message' => 'Password updated successfully.', 'type' => 'success'];
        } else {
            $_SESSION['alert'] = ['message' => 'Failed to update password. Please try again.', 'type' => 'danger'];
        }
        $stmt->close();
    }
}
?>

<link rel="stylesheet" href="../assets/css/user_dashboard.css">
<link rel="stylesheet" href="../assets/css/settings.css">
<main>
    <?php alertMessage(); ?>
    <div class="page-header">
        <h1>Settings</h1>
        <small>Manage your account settings here.</small>
    </div>
    <div class="page-content">
        <div class="analytics">
            <div class="card">
                <div class="card-head">
                    <h2>Personal Information</h2>
                </div>
                <div class="card-progress">
                    <small>Name: <?= htmlspecialchars($user_data['lastname'] . ', ' . $user_data['firstname']); ?></small><br>
                    <small>Email: <?= htmlspecialchars($user_data['email']); ?></small><br>
                    <small>Contact Number: <?= htmlspecialchars($user_data['number']); ?></small>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#personalInfoModal">
                        View Details
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2>Payment Settings</h2>
                </div>
                <div class="card-progress">
                    <small>Saved Method: Gcash (*******2892)</small>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#paymentSettingsModal">
                        Manage Payment Methods
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2>Account Security</h2>
                </div>
                <div class="card-progress">
                    <small>Change Password?</small>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#accountSecurityModal">
                        Update Password
                    </button>
                </div>
            </div>
            <div class="card">
                <div class="card-head">
                    <h2>Privacy and Terms</h2>
                </div>
                <div class="card-progress">
                    <small>View our Privacy Policy and Terms of Service</small>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#privacyTermsModal">
                        View Details
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Personal Information Modal -->
<div class="modal fade" id="personalInfoModal" tabindex="-1" aria-labelledby="personalInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="personalInfoModalLabel">Personal Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input name="firstname" value="<?= htmlspecialchars($user_data['firstname']); ?>" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input name="lastname" value="<?= htmlspecialchars($user_data['lastname']); ?>" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input name="email" value="<?= htmlspecialchars($user_data['email']); ?>" type="email" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number</label>
                        <input name="number" value="<?= htmlspecialchars($user_data['number']); ?>" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Student ID</label>
                        <input name="student_id" value="<?= htmlspecialchars($user_data['studentid']); ?>" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Year Level</label>
                        <input name="year_level" value="<?= htmlspecialchars($user_data['year_level']); ?>" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Course</label>
                        <input name="course_id" value="<?= htmlspecialchars($user_data['course_id']); ?>" type="text" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Section</label>
                        <input name="section_id" value="<?= htmlspecialchars($user_data['section_id']); ?>" type="text" class="form-control" readonly>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Birthdate</label>
                        <input name="birthdate" value="<?= htmlspecialchars($user_data['birthdate']); ?>" type="text" class="form-control" readonly>
                    </div>
                </div>
                <small class="text-muted">To edit your details, please contact support or visit the user edit page.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Account Security Modal -->
<div class="modal fade" id="accountSecurityModal" tabindex="-1" aria-labelledby="accountSecurityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accountSecurityModalLabel">Account Security</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="update_password" value="1">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Settings Modal -->
<div class="modal fade" id="paymentSettingsModal" tabindex="-1" aria-labelledby="paymentSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentSettingsModalLabel">Payment Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Saved Payment Methods</h6>
                <p>Gcash (*******2892)</p>
                <hr>
                <h6>Add a New Payment Method</h6>
                <form>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control" name="payment_method">
                            <option value="gcash">Gcash</option>
                            <option value="paypal">PayPal</option>
                            <option value="credit_card">Credit Card</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Number</label>
                        <input type="text" name="account_number" class="form-control" placeholder="Enter account number">
                    </div>
                    <button type="button" class="btn btn-primary">Add Method</button>
                </form>
                <small class="text-muted">Note: This is a simulated form. Actual implementation requires a backend script to handle payment methods.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy and Terms Modal -->
<div class="modal fade" id="privacyTermsModal" tabindex="-1" aria-labelledby="privacyTermsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyTermsModalLabel">Privacy and Terms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Privacy Policy</h6>
                <p>
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                    Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                </p>
                <hr>
                <h6>Terms and Conditions</h6>
                <p>
                    Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
                    Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                </p>
                <small class="text-muted">Note: This is placeholder content. Replace with your actual Privacy Policy and Terms of Service.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>