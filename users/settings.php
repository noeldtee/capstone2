<?php
// Start the session
session_start();

// Include header
$page_title = "Settings";
include('includes/header.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['student'])) {
    $_SESSION['alert'] = ['message' => 'Please log in as a student to manage your settings.', 'type' => 'danger'];
    header('Location: /capstone-admin/index.php');
    exit;
}

// Verify database connection
if (!$conn) {
    $_SESSION['alert'] = ['message' => 'Database connection failed.', 'type' => 'danger'];
    header('Location: /capstone-admin/index.php');
    exit;
}

// Fetch user data from the database
$user_id = $_SESSION['user_id'];
$user_data = null;

$stmt = $conn->prepare("
    SELECT firstname, middlename, lastname, email, number, studentid, year_level, course_id, section_id, birthdate, gender, password, profile, role
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
    header('Location: /capstone-admin/index.php');
    exit;
}
$stmt->close();

// Fetch Terms and Conditions from settings
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terms_and_conditions'");
$stmt->execute();
$result = $stmt->get_result();
$terms_and_conditions = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '<p>No Terms and Conditions set. Please contact the administrator.</p>';
$stmt->close();

// Handle personal information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal_info'])) {
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $number = trim($_POST['number']);
    $birthdate = $_POST['birthdate'];
    $gender = $_POST['gender'];
    $profile = $_FILES['profile'] ?? null;

    // Validate inputs
    if (empty($firstname) || empty($middlename) || empty($lastname) || empty($email) || empty($number) || empty($birthdate) || empty($gender)) {
        $_SESSION['alert'] = ['message' => 'All fields are required.', 'type' => 'danger'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['alert'] = ['message' => 'Invalid email format.', 'type' => 'danger'];
    } elseif (!preg_match('/^((\+63[0-9]{10})|(09[0-9]{9}))$/', $number)) {
        $_SESSION['alert'] = ['message' => 'Phone number must start with +63 followed by 10 digits (e.g., +639123456789) or start with 09 followed by 9 digits (e.g., 09123456789).', 'type' => 'danger'];
    } else {
        // Validate birthdate (14–40 years)
        $birthdate_dt = new DateTime($birthdate);
        $now = new DateTime();
        $age = $now->diff($birthdate_dt)->y;
        if ($age < 14 || $age > 40) {
            $_SESSION['alert'] = ['message' => 'Age must be between 14 and 40 years.', 'type' => 'danger'];
        } else {
            // Check for email and number uniqueness (excluding the current user)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['alert'] = ['message' => 'Email is already taken.', 'type' => 'danger'];
                $stmt->close();
            } else {
                $stmt->close();
                $stmt = $conn->prepare("SELECT id FROM users WHERE number = ? AND id != ? LIMIT 1");
                $stmt->bind_param("si", $number, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $_SESSION['alert'] = ['message' => 'Phone number is already taken.', 'type' => 'danger'];
                    $stmt->close();
                } else {
                    $stmt->close();

                    // Handle profile picture upload
                    $profilePath = $user_data['profile']; // Default to existing profile
                    if ($profile && $profile['error'] == 0) {
                        $fileSize = $profile['size'];
                        $fileType = mime_content_type($profile['tmp_name']);
                        if ($fileSize > 2000000) {
                            $_SESSION['alert'] = ['message' => 'Profile picture must be less than 2MB.', 'type' => 'danger'];
                        } elseif (!str_starts_with($fileType, 'image/')) {
                            $_SESSION['alert'] = ['message' => 'Only image files are allowed for profile picture.', 'type' => 'danger'];
                        } else {
                            $fileName = time() . "_" . basename($profile['name']);
                            $target = "assets/images/" . $fileName;
                            if (!file_exists('assets/images')) {
                                if (!mkdir('assets/images', 0777, true)) {
                                    error_log("Failed to create directory: assets/images");
                                    $_SESSION['alert'] = ['message' => 'Failed to create directory for profile picture.', 'type' => 'danger'];
                                }
                            }
                            if (!isset($_SESSION['alert']) && !is_writable('assets/images')) {
                                error_log("Directory assets/images is not writable");
                                $_SESSION['alert'] = ['message' => 'Directory for profile pictures is not writable.', 'type' => 'danger'];
                            } elseif (!isset($_SESSION['alert'])) {
                                if (move_uploaded_file($profile['tmp_name'], $target)) {
                                    $profilePath = $target;
                                    // Delete old profile picture if it's not the default
                                    if ($user_data['profile'] !== 'assets/images/default_profile.png' && file_exists($user_data['profile'])) {
                                        unlink($user_data['profile']);
                                    }
                                } else {
                                    error_log("Failed to upload profile picture to $target");
                                    $_SESSION['alert'] = ['message' => 'Failed to upload profile picture.', 'type' => 'danger'];
                                }
                            }
                        }
                    }

                    // If no errors, update the user data
                    if (!isset($_SESSION['alert'])) {
                        $stmt = $conn->prepare("UPDATE users SET firstname = ?, middlename = ?, lastname = ?, email = ?, number = ?, birthdate = ?, gender = ?, profile = ? WHERE id = ?");
                        if (!$stmt) {
                            error_log("Prepare failed: " . $conn->error);
                            $_SESSION['alert'] = ['message' => 'Failed to prepare statement: ' . $conn->error, 'type' => 'danger'];
                        } else {
                            $stmt->bind_param("ssssssssi", $firstname, $middlename, $lastname, $email, $number, $birthdate, $gender, $profilePath, $user_id);
                            if ($stmt->execute()) {
                                $_SESSION['alert'] = ['message' => 'Personal information updated successfully.', 'type' => 'success'];

                                // Update session data
                                $user_data['firstname'] = $firstname;
                                $user_data['middlename'] = $middlename;
                                $user_data['lastname'] = $lastname;
                                $user_data['email'] = $email;
                                $user_data['number'] = $number;
                                $user_data['birthdate'] = $birthdate;
                                $user_data['gender'] = $gender;
                                $user_data['profile'] = $profilePath;

                                // Set a flag to indicate a refresh is needed
                                $_SESSION['needs_refresh'] = true;
                            } else {
                                error_log("Update failed: " . $stmt->error);
                                $_SESSION['alert'] = ['message' => 'Failed to update personal information: ' . $stmt->error, 'type' => 'danger'];
                                $_SESSION['needs_refresh'] = true;
                            }
                            $stmt->close();
                        }
                    } else {
                        // If there was an error, still set the refresh flag
                        $_SESSION['needs_refresh'] = true;
                    }
                }
            }
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['alert'] = ['message' => 'All fields are required.', 'type' => 'danger'];
        $_SESSION['needs_refresh'] = true;
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['alert'] = ['message' => 'New password and confirmation do not match.', 'type' => 'danger'];
        $_SESSION['needs_refresh'] = true;
    } elseif (strlen($new_password) < 8 || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $_SESSION['alert'] = ['message' => 'New password must be at least 8 characters long and include at least one number and one special character (e.g., !@#$%^&*).', 'type' => 'danger'];
        $_SESSION['needs_refresh'] = true;
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $_SESSION['alert'] = ['message' => 'Current password is incorrect.', 'type' => 'danger'];
        $_SESSION['needs_refresh'] = true;
    } else {
        // Hash the new password and update the database
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $_SESSION['alert'] = ['message' => 'Failed to prepare statement: ' . $conn->error, 'type' => 'danger'];
            $_SESSION['needs_refresh'] = true;
        } else {
            $stmt->bind_param("si", $hashed_password, $user_id);
            if ($stmt->execute()) {
                $_SESSION['alert'] = ['message' => 'Password updated successfully.', 'type' => 'success'];
                $_SESSION['needs_refresh'] = true;
            } else {
                error_log("Update failed: " . $stmt->error);
                $_SESSION['alert'] = ['message' => 'Failed to update password: ' . $stmt->error, 'type' => 'danger'];
                $_SESSION['needs_refresh'] = true;
            }
            $stmt->close();
        }
    }
}

// Fetch course and section names for display
$course_name = "Not Set";
$section_name = "Not Set";

if (!empty($user_data['course_id'])) {
    $stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $stmt->bind_param("i", $user_data['course_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $course_name = $result->fetch_assoc()['name'];
    }
    $stmt->close();
}

if (!empty($user_data['section_id'])) {
    $stmt = $conn->prepare("SELECT section FROM sections WHERE id = ?");
    $stmt->bind_param("i", $user_data['section_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $section_name = $result->fetch_assoc()['section'];
    }
    $stmt->close();
}
?>

<link rel="stylesheet" href="../assets/css/user_dashboard.css">
<link rel="stylesheet" href="../assets/css/settings.css">
<main>
    <?php
    // Display alert and refresh page if needed
    if (isset($_SESSION['alert'])) {
        echo '<div class="alert alert-' . $_SESSION['alert']['type'] . '">';
        echo $_SESSION['alert']['message'];
        echo '</div>';

        // Check if a refresh is needed and hasn't been done yet
        if (isset($_SESSION['needs_refresh']) && $_SESSION['needs_refresh'] === true) {
            // Reset the refresh flag to prevent further refreshes
            $_SESSION['needs_refresh'] = false;
            // JavaScript to refresh the page after 2 seconds
            echo '<script>
                setTimeout(function() {
                    // Replace the current history entry to prevent form resubmission
                    history.replaceState(null, null, window.location.href);
                    location.reload();
                }, 2000);
            </script>';
        }

        // Unset the alert after displaying it
        unset($_SESSION['alert']);
    }

    // Call alertMessage() only if no alert was already displayed
    if (!isset($_SESSION['alert'])) {
        alertMessage();
    }
    ?>
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
                    <small>Name: <?= htmlspecialchars($user_data['lastname'] . ', ' . $user_data['firstname'] . ' ' . $user_data['middlename']); ?></small><br>
                    <small>Email: <?= htmlspecialchars($user_data['email']); ?></small><br>
                    <small>Contact Number: <?= htmlspecialchars($user_data['number']); ?></small>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#personalInfoModal">
                        Edit Details
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
                <h5 class="modal-title" id="personalInfoModalLabel">Edit Personal Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="personalInfoForm">
                    <input type="hidden" name="update_personal_info" value="1">
                    <!-- Profile Picture -->
                    <div class="mb-3 text-center">
                        <label for="profile" class="form-label">Profile Picture</label>
                        <input type="file" name="profile" id="profile" class="form-control" accept="image/*" onchange="previewImage(event)">
                        <img id="profile-preview" src="<?= htmlspecialchars($user_data['profile']); ?>" alt="Profile Picture" style="max-width: 200px; margin-top: 10px; <?= $user_data['profile'] ? '' : 'display: none;'; ?>">
                        <button type="button" id="remove-profile" class="btn btn-danger btn-sm mt-2" style="display: <?= $user_data['profile'] ? 'inline-block' : 'none'; ?>;" onclick="removeImage()">Remove Image</button>
                        <small class="form-text text-muted">Leave blank to keep current picture. (Max 2MB)</small>
                    </div>
                    <!-- First Name, Middle Name, Last Name -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input name="firstname" value="<?= htmlspecialchars($user_data['firstname']); ?>" type="text" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input name="middlename" value="<?= htmlspecialchars($user_data['middlename']); ?>" type="text" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input name="lastname" value="<?= htmlspecialchars($user_data['lastname']); ?>" type="text" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input name="email" value="<?= htmlspecialchars($user_data['email']); ?>" type="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input name="number" value="<?= htmlspecialchars($user_data['number']); ?>" type="text" class="form-control" required pattern="(\+63[0-9]{10}|09[0-9]{9})" title="Enter a Philippine number starting with +63 followed by 10 digits (e.g., +639123456789) or starting with 09 followed by 9 digits (e.g., 09123456789)">
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
                            <input name="course_id" value="<?= htmlspecialchars($course_name); ?>" type="text" class="form-control" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Section</label>
                            <input name="section_id" value="<?= htmlspecialchars($section_name); ?>" type="text" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input name="birthdate" value="<?= htmlspecialchars($user_data['birthdate']); ?>" type="date" class="form-control" required max="<?= date('Y-m-d', strtotime('-14 years')); ?>" min="<?= date('Y-m-d', strtotime('-40 years')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?= $user_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?= $user_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Prefer not to say" <?= $user_data['gender'] === 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
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
                <form method="POST" id="accountSecurityForm">
                    <input type="hidden" name="update_password" value="1">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" required pattern="(?=.*[0-9])(?=.*[!@#$%^&*(),.?:{}|<>]).{8,}" title="Password must be at least 8 characters and include a number and a special character">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <div id="password-error" class="text-danger mt-1" style="font-size: 0.875rem;"></div>
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
                <h6>Terms and Conditions</h6>
                <?= $terms_and_conditions ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Profile picture preview
const profileInput = document.getElementById('profile');
const profilePreview = document.getElementById('profile-preview');
const removeBtn = document.getElementById('remove-profile');

function previewImage(event) {
    if (event.target.files && event.target.files[0]) {
        profilePreview.src = URL.createObjectURL(event.target.files[0]);
        profilePreview.style.display = 'block';
        removeBtn.style.display = 'inline-block';
    }
}

function removeImage() {
    profileInput.value = '';
    profilePreview.style.display = 'none';
    profilePreview.src = '#';
    removeBtn.style.display = 'none';
}

// Password validation
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const passwordError = document.getElementById('password-error');

function validatePasswords() {
    if (newPassword.value !== confirmPassword.value) {
        confirmPassword.setCustomValidity('Passwords do not match.');
        passwordError.textContent = 'Passwords do not match.';
    } else {
        confirmPassword.setCustomValidity('');
        passwordError.textContent = '';
    }
}

if (newPassword && confirmPassword) {
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
}

// Close modals after form submission (Bootstrap 5)
document.getElementById('personalInfoForm').addEventListener('submit', function(event) {
    setTimeout(function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('personalInfoModal'));
        modal.hide();
    }, 500);
});

document.getElementById('accountSecurityForm').addEventListener('submit', function(event) {
    setTimeout(function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('accountSecurityModal'));
        modal.hide();
    }, 500);
});

// Reset forms on modal close to prevent resubmission
document.getElementById('personalInfoModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('personalInfoForm').reset();
    // Reset profile picture preview
    if (profilePreview.src !== '<?= htmlspecialchars($user_data['profile']); ?>') {
        profilePreview.src = '<?= htmlspecialchars($user_data['profile']); ?>';
        profilePreview.style.display = '<?= $user_data['profile'] ? 'block' : 'none'; ?>';
        removeBtn.style.display = '<?= $user_data['profile'] ? 'inline-block' : 'none'; ?>';
    }
});

document.getElementById('accountSecurityModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('accountSecurityForm').reset();
    passwordError.textContent = '';
    confirmPassword.setCustomValidity('');
});
</script>

<?php include('includes/footer.php'); ?>