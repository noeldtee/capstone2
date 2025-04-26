<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../config/function.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'registrar') {
    redirect('../index.php', 'You must be logged in as a registrar to perform this action.', 'danger');
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        if (
            empty($_POST['firstname']) || empty($_POST['lastname']) || empty($_POST['email']) ||
            empty($_POST['number']) || empty($_POST['password']) || empty($_POST['role']) || !isset($_POST['is_ban'])
        ) {
            redirect('registrar.php', 'All fields are required.', 'danger');
        }

        $firstname = validate($_POST['firstname']);
        $lastname = validate($_POST['lastname']);
        $email = validate($_POST['email']);
        $number = validate($_POST['number']);
        $password = validate($_POST['password']);
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);
        $verify_status = 1; // Automatically set verify_status to 1 for registrars

        if (!preg_match('/^09[0-9]{9}$/', $number)) {
            redirect('registrar.php', 'Phone number must start with 09 and be 11 digits long.', 'danger');
        }

        if (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
            redirect('registrar.php', 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('registrar.php', 'Email already exists.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM users WHERE number = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('registrar.php', 'Phone number already exists.', 'danger');
        }
        $stmt->close();

        if (!in_array($role, ['registrar', 'staff', 'cashier'])) {
            redirect('registrar.php', 'Invalid role.', 'danger');
        }

        if (!in_array($is_ban, [0, 1])) {
            redirect('registrar.php', 'Invalid status.', 'danger');
        }

        // Handle profile image upload
        $profile = null; // Default to NULL
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB

            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                redirect('registrar.php?' . http_build_query($_GET), 'Only JPG and PNG files are allowed.', 'danger');
            }

            // Validate file size
            if ($file['size'] > $max_size) {
                redirect('registrar.php?' . http_build_query($_GET), 'File size must not exceed 2MB.', 'danger');
            }

            // Generate unique filename (using a temporary ID placeholder since we don't have the user ID yet)
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_new_' . time() . '.' . $ext;
            $upload_dir = '../assets/images/';
            $upload_path = $upload_dir . $filename;
            $db_path = 'assets/images/' . $filename;

            // Move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $profile = $db_path;
            } else {
                redirect('registrar.php?' . http_build_query($_GET), 'Failed to upload profile image.', 'danger');
            }
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert the user with verify_status set to 1
        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, number, profile, password, role, is_ban, verify_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssii", $firstname, $lastname, $email, $number, $profile, $hashed_password, $role, $is_ban, $verify_status);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id; // Get the ID of the newly inserted user

            // If a profile image was uploaded, rename it to include the user ID
            if ($profile) {
                $ext = pathinfo($profile, PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $new_upload_path = $upload_dir . $new_filename;
                $new_db_path = 'assets/images/' . $new_filename;

                if (rename($upload_path, $new_upload_path)) {
                    // Update the database with the new profile path
                    $stmt_update = $conn->prepare("UPDATE users SET profile = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_db_path, $user_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
            }
            redirect('registrar.php?' . http_build_query($_GET), 'Registrar added successfully.', 'success');
        } else {
            // If insertion fails and an image was uploaded, clean up the uploaded file
            if ($profile && file_exists($upload_path)) {
                unlink($upload_path);
            }
            redirect('registrar.php?' . http_build_query($_GET), 'Failed to add registrar: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'edit':
        if (
            empty($_POST['id']) || empty($_POST['firstname']) || empty($_POST['lastname']) ||
            empty($_POST['email']) || empty($_POST['number']) || empty($_POST['role']) || !isset($_POST['is_ban'])
        ) {
            redirect('registrar.php?' . http_build_query($_GET), 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $firstname = validate($_POST['firstname']);
        $lastname = validate($_POST['lastname']);
        $email = validate($_POST['email']);
        $number = validate($_POST['number']);
        $current_profile = validate($_POST['profile']);
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);

        if (!preg_match('/^09[0-9]{9}$/', $number)) {
            redirect('registrar.php?' . http_build_query($_GET), 'Phone number must start with 09 and be 11 digits long.', 'danger');
        }

        if (!empty($password) && (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password))) {
            redirect('registrar.php?' . http_build_query($_GET), 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('registrar.php?' . http_build_query($_GET), 'Email already exists.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM users WHERE number = ? AND id != ?");
        $stmt->bind_param("si", $number, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('registrar.php?' . http_build_query($_GET), 'Phone number already exists.', 'danger');
        }
        $stmt->close();

        if (!in_array($role, ['registrar', 'staff', 'cashier'])) {
            redirect('registrar.php?' . http_build_query($_GET), 'Invalid role.', 'danger');
        }

        if (!in_array($is_ban, [0, 1])) {
            redirect('registrar.php?' . http_build_query($_GET), 'Invalid status.', 'danger');
        }

        // Handle profile image upload
        $profile = $current_profile; // Default to current profile
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB

            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                redirect('registrar.php?' . http_build_query($_GET), 'Only JPG and PNG files are allowed.', 'danger');
            }

            // Validate file size
            if ($file['size'] > $max_size) {
                redirect('registrar.php?' . http_build_query($_GET), 'File size must not exceed 2MB.', 'danger');
            }

            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $id . '_' . time() . '.' . $ext;
            $upload_dir = '../assets/images/';
            $upload_path = $upload_dir . $filename;
            $db_path = 'assets/images/' . $filename;

            // Move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old profile image if it exists
                if ($current_profile && file_exists('../' . $current_profile)) {
                    unlink('../' . $current_profile);
                }
                $profile = $db_path;
            } else {
                redirect('registrar.php?' . http_build_query($_GET), 'Failed to upload profile image.', 'danger');
            }
        }

        // Update the user record
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, number = ?, profile = ?, password = ?, role = ?, is_ban = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssssii", $firstname, $lastname, $email, $number, $profile, $hashed_password, $role, $is_ban, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, number = ?, profile = ?, role = ?, is_ban = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssssii", $firstname, $lastname, $email, $number, $profile, $role, $is_ban, $id);
        }

        if ($stmt->execute()) {
            redirect('registrar.php?' . http_build_query($_GET), 'Registrar updated successfully.', 'success');
        } else {
            redirect('registrar.php?' . http_build_query($_GET), 'Failed to update registrar: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'delete':
        if (!isset($_POST['id']) || !is_numeric($_POST['id']) || (int)$_POST['id'] <= 0) {
            redirect('registrar.php?' . http_build_query($_GET), 'Invalid registrar ID.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        
        // Fetch the current profile image to delete it if it exists
        $stmt = $conn->prepare("SELECT profile FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $profile = $user['profile'];
            if ($profile && file_exists('../' . $profile)) {
                unlink('../' . $profile);
            }
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            redirect('registrar.php?' . http_build_query($_GET), 'Registrar deleted successfully.', 'success');
        } else {
            redirect('registrar.php?' . http_build_query($_GET), 'Failed to delete registrar: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'get':
        while (ob_get_level()) {
            ob_end_clean();
        }
        ini_set('display_errors', 0);
        error_reporting(E_ALL);

        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            $response = ['status' => 400, 'message' => 'Invalid registrar ID.'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $id = (int)validate($_GET['id']);
        if (!$conn) {
            $response = ['status' => 500, 'message' => 'Database connection failed.'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, firstname, lastname, email, number, profile, role, is_ban FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response = ['status' => 200, 'message' => 'Registrar fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'Registrar not found.'];
        }

        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    default:
        redirect('registrar.php', 'Invalid action.', 'danger');
        break;
}
?>