<?php
// Start the session
session_start();

// Include necessary configurations and functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/function.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle form submissions (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle personal information update
    if (isset($_POST['update_personal_info'])) {
        $user_id = $_SESSION['user_id'];
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
            redirect('settings.php', 'All fields are required.', 'danger');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('settings.php', 'Invalid email format.', 'danger');
        } elseif (!preg_match('/^((\+63[0-9]{10})|(09[0-9]{9}))$/', $number)) {
            redirect('settings.php', 'Phone number must start with +63 followed by 10 digits (e.g., +639123456789) or start with 09 followed by 9 digits (e.g., 09123456789).', 'danger');
        } else {
            // Validate birthdate (14–40 years)
            $birthdate_dt = new DateTime($birthdate);
            $now = new DateTime();
            $age = $now->diff($birthdate_dt)->y;
            if ($age < 14 || $age > 40) {
                redirect('settings.php', 'Age must be between 14 and 40 years.', 'danger');
            } else {
                // Check for email and number uniqueness (excluding the current user)
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    redirect('settings.php', 'Email is already taken.', 'danger');
                    $stmt->close();
                } else {
                    $stmt->close();
                    $stmt = $conn->prepare("SELECT id FROM users WHERE number = ? AND id != ? LIMIT 1");
                    $stmt->bind_param("si", $number, $user_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        redirect('settings.php', 'Phone number is already taken.', 'danger');
                        $stmt->close();
                    } else {
                        $stmt->close();

                        // Handle profile picture upload
                        $profilePath = null;
                        // Fetch current profile picture
                        $stmt = $conn->prepare("SELECT profile FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $current_profile = $stmt->get_result()->fetch_assoc()['profile'];
                        $stmt->close();
                        $profilePath = $current_profile; // Default to existing profile
                        if ($profile && $profile['error'] == 0) {
                            $fileSize = $profile['size'];
                            $fileType = mime_content_type($profile['tmp_name']);
                            if ($fileSize > 2000000) {
                                redirect('settings.php', 'Profile picture must be less than 2MB.', 'danger');
                            } elseif (!str_starts_with($fileType, 'image/')) {
                                redirect('settings.php', 'Only image files are allowed for profile picture.', 'danger');
                            } else {
                                $fileName = time() . "_" . basename($profile['name']);
                                // Use the correct relative path from capstone-admin/users/ to capstone-admin/assets/images/
                                $targetDir = "../assets/images/";
                                $target = $targetDir . $fileName;
                                // Check if the directory exists
                                if (!file_exists($targetDir)) {
                                    if (!mkdir($targetDir, 0777, true)) {
                                        error_log("Failed to create directory: $targetDir");
                                        redirect('settings.php', 'Failed to create directory for profile picture.', 'danger');
                                    }
                                }
                                // Check if the directory is writable
                                if (!is_writable($targetDir)) {
                                    error_log("Directory $targetDir is not writable");
                                    redirect('settings.php', 'Directory for profile pictures is not writable.', 'danger');
                                } else {
                                    if (move_uploaded_file($profile['tmp_name'], $target)) {
                                        // Store the path relative to capstone-admin/ in the database
                                        $profilePath = "assets/images/" . $fileName;
                                        // Delete old profile picture if it's not the default
                                        if ($current_profile !== 'assets/images/default_profile.png' && file_exists($current_profile)) {
                                            unlink($current_profile);
                                        }
                                    } else {
                                        error_log("Failed to upload profile picture to $target");
                                        redirect('settings.php', 'Failed to upload profile picture.', 'danger');
                                    }
                                }
                            }
                        }

                        // Update the user data
                        $stmt = $conn->prepare("UPDATE users SET firstname = ?, middlename = ?, lastname = ?, email = ?, number = ?, birthdate = ?, gender = ?, profile = ? WHERE id = ?");
                        if (!$stmt) {
                            error_log("Prepare failed: " . $conn->error);
                            redirect('settings.php', 'Failed to prepare statement: ' . $conn->error, 'danger');
                        } else {
                            $stmt->bind_param("ssssssssi", $firstname, $middlename, $lastname, $email, $number, $birthdate, $gender, $profilePath, $user_id);
                            if ($stmt->execute()) {
                                redirect('settings.php', 'Personal information updated successfully.', 'success');
                            } else {
                                error_log("Update failed: " . $stmt->error);
                                redirect('settings.php', 'Failed to update personal information: ' . $stmt->error, 'danger');
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    // Handle password update
    if (isset($_POST['update_password'])) {
        $user_id = $_SESSION['user_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Fetch current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            redirect('settings.php', 'All fields are required.', 'danger');
        } elseif ($new_password !== $confirm_password) {
            redirect('settings.php', 'New password and confirmation do not match.', 'danger');
        } elseif (strlen($new_password) < 8 || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            redirect('settings.php', 'New password must be at least 8 characters long and include at least one number and one special character (e.g., !@#$%^&*).', 'danger');
        } elseif (!password_verify($current_password, $user_data['password'])) {
            redirect('settings.php', 'Current password is incorrect.', 'danger');
        } else {
            // Hash the new password and update the database
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                redirect('settings.php', 'Failed to prepare statement: ' . $conn->error, 'danger');
            } else {
                $stmt->bind_param("si", $hashed_password, $user_id);
                if ($stmt->execute()) {
                    redirect('settings.php', 'Password updated successfully.', 'success');
                } else {
                    error_log("Update failed: " . $stmt->error);
                    redirect('settings.php', 'Failed to update password: ' . $stmt->error, 'danger');
                }
                $stmt->close();
            }
        }
    }
}

// Verify database connection
if (!$conn) {
    redirect('/capstone-admin/index.php', 'Database connection failed.', 'danger');
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
    redirect('/capstone-admin/index.php', 'User not found.', 'danger');
}
$stmt->close();

// Fetch Terms and Conditions from settings
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'terms_and_conditions'");
$stmt->execute();
$result = $stmt->get_result();
$terms_and_conditions = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '<p>No Terms and Conditions set. Please contact the administrator.</p>';
$stmt->close();

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

// Include header after all redirects
$page_title = "Settings";
include('includes/header.php');
?>

<link rel="stylesheet" href="../assets/css/user_dashboard.css">
<link rel="stylesheet" href="../assets/css/settings.css">
<main>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>
    <div class="page-header">
        <h1>Settings</h1>
        <small>Manage your account settings here.</small>
    </div>
    <div class="page-content">
        <div class="analytics">
            <!-- Personal Information Card -->
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
            <!-- Academic Information Card -->
            <div class="card">
                <div class="card-head">
                    <h2>Academic Information</h2>
                </div>
                <div class="card-progress">
                    <small>Student ID: <?= htmlspecialchars($user_data['studentid']); ?></small><br>
                    <small>Year Level: <?= htmlspecialchars($user_data['year_level']); ?></small><br>
                    <small>Course: <?= htmlspecialchars($course_name); ?></small><br>
                    <small>Section: <?= htmlspecialchars($section_name); ?></small><br>
                    <small class="text-muted">These fields are managed by the administrator. Contact support to update.</small>
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#academicInfoModal">
                        Academic Information
                    </button>
                </div>
            </div>
            <!-- Account Security Card -->
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
            <!-- Privacy and Terms Card -->
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
                        <img id="profile-preview" src="<?php echo $user_data['profile'] ? '../' . htmlspecialchars($user_data['profile']) : '../assets/images/default_profile.png'; ?>" alt="Profile Picture" style="max-width: 200px; margin-top: 10px; <?php echo $user_data['profile'] ? '' : 'display: none;'; ?>">
                        <button type="button" id="remove-profile" class="btn btn-danger btn-sm mt-2" style="display: <?php echo $user_data['profile'] ? 'inline-block' : 'none'; ?>;" onclick="removeImage()">Remove Image</button>
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
                    <button type="submit" class="btn btn-primary" id="personalInfoSubmit">
                        <span id="personalInfoText">Save Changes</span>
                        <span id="personalInfoSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Academic Information Modal -->
<div class="modal fade" id="academicInfoModal" tabindex="-1" aria-labelledby="academicInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="academicInfoModalLabel">Academic Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                These fields are managed by the administrator. Please contact support to request changes.
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
                    <button type="submit" class="btn btn-primary" id="accountSecuritySubmit">
                        <span id="accountSecurityText">Update Password</span>
                        <span id="accountSecuritySpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </form>
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

// Loading spinner and modal close for Personal Information form
document.getElementById('personalInfoForm').addEventListener('submit', function(event) {
    const submitButton = document.getElementById('personalInfoSubmit');
    const submitText = document.getElementById('personalInfoText');
    const spinner = document.getElementById('personalInfoSpinner');
    
    submitButton.disabled = true;
    submitText.classList.add('d-none');
    spinner.classList.remove('d-none');
    
    setTimeout(function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('personalInfoModal'));
        modal.hide();
    }, 500);
});

// Loading spinner and modal close for Account Security form
document.getElementById('accountSecurityForm').addEventListener('submit', function(event) {
    const submitButton = document.getElementById('accountSecuritySubmit');
    const submitText = document.getElementById('accountSecurityText');
    const spinner = document.getElementById('accountSecuritySpinner');
    
    submitButton.disabled = true;
    submitText.classList.add('d-none');
    spinner.classList.remove('d-none');
    
    setTimeout(function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('accountSecurityModal'));
        modal.hide();
    }, 500);
});

// Reset forms on modal close to prevent resubmission
document.getElementById('personalInfoModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('personalInfoForm').reset();
    // Reset form inputs to original values
    document.querySelector('#personalInfoForm input[name="firstname"]').value = '<?= htmlspecialchars($user_data['firstname']); ?>';
    document.querySelector('#personalInfoForm input[name="middlename"]').value = '<?= htmlspecialchars($user_data['middlename']); ?>';
    document.querySelector('#personalInfoForm input[name="lastname"]').value = '<?= htmlspecialchars($user_data['lastname']); ?>';
    document.querySelector('#personalInfoForm input[name="email"]').value = '<?= htmlspecialchars($user_data['email']); ?>';
    document.querySelector('#personalInfoForm input[name="number"]').value = '<?= htmlspecialchars($user_data['number']); ?>';
    document.querySelector('#personalInfoForm input[name="birthdate"]').value = '<?= htmlspecialchars($user_data['birthdate']); ?>';
    document.querySelector('#personalInfoForm select[name="gender"]').value = '<?= htmlspecialchars($user_data['gender']); ?>';
    // Reset profile picture preview
    if (profilePreview.src !== '<?php echo $user_data['profile'] ? '../' . htmlspecialchars($user_data['profile']) : '../assets/images/default_profile.png'; ?>') {
        profilePreview.src = '<?php echo $user_data['profile'] ? '../' . htmlspecialchars($user_data['profile']) : '../assets/images/default_profile.png'; ?>';
        profilePreview.style.display = '<?php echo $user_data['profile'] ? 'block' : 'none'; ?>';
        removeBtn.style.display = '<?php echo $user_data['profile'] ? 'inline-block' : 'none'; ?>';
    }
    // Reset submit button
    const submitButton = document.getElementById('personalInfoSubmit');
    const submitText = document.getElementById('personalInfoText');
    const spinner = document.getElementById('personalInfoSpinner');
    submitButton.disabled = false;
    submitText.classList.remove('d-none');
    spinner.classList.add('d-none');
});

document.getElementById('accountSecurityModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('accountSecurityForm').reset();
    passwordError.textContent = '';
    confirmPassword.setCustomValidity('');
    // Reset submit button
    const submitButton = document.getElementById('accountSecuritySubmit');
    const submitText = document.getElementById('accountSecurityText');
    const spinner = document.getElementById('accountSecuritySpinner');
    submitButton.disabled = false;
    submitText.classList.remove('d-none');
    spinner.classList.add('d-none');
});
</script>

<?php include('includes/footer.php'); ?>