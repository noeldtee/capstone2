<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../config/function.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect('../index.php', 'You must be logged in as an admin to perform this action.', 'danger');
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch ($action) {
    case 'add':
        if (
            empty($_POST['firstname']) || empty($_POST['lastname']) || empty($_POST['email']) ||
            empty($_POST['number']) || empty($_POST['password']) || empty($_POST['role']) || !isset($_POST['is_ban'])
        ) {
            redirect('admin.php', 'All fields are required.', 'danger');
        }

        $firstname = validate($_POST['firstname']);
        $lastname = validate($_POST['lastname']);
        $email = validate($_POST['email']);
        $number = validate($_POST['number']);
        $password = validate($_POST['password']);
        $profile = validate($_POST['profile']) ?: '../assets/images/default_profile.png';
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);

        if (!preg_match('/^09[0-9]{9}$/', $number)) {
            redirect('admin.php', 'Phone number must start with 09 and be 11 digits long.', 'danger');
        }

        if (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
            redirect('admin.php', 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('admin.php', 'Email already exists.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM users WHERE number = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('admin.php', 'Phone number already exists.', 'danger');
        }
        $stmt->close();

        if (!in_array($role, ['admin', 'registrar', 'cashier'])) {
            redirect('admin.php', 'Invalid role.', 'danger');
        }

        if (!in_array($is_ban, [0, 1])) {
            redirect('admin.php', 'Invalid status.', 'danger');
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, number, profile, password, role, is_ban, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssssi", $firstname, $lastname, $email, $number, $profile, $hashed_password, $role, $is_ban);
        if ($stmt->execute()) {
            redirect('admin.php?' . http_build_query($_GET), 'Admin added successfully.', 'success');
        } else {
            redirect('admin.php?' . http_build_query($_GET), 'Failed to add admin: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'edit':
        if (
            empty($_POST['id']) || empty($_POST['firstname']) || empty($_POST['lastname']) ||
            empty($_POST['email']) || empty($_POST['number']) || empty($_POST['role']) || !isset($_POST['is_ban'])
        ) {
            redirect('admin.php?' . http_build_query($_GET), 'All fields are required.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $firstname = validate($_POST['firstname']);
        $lastname = validate($_POST['lastname']);
        $email = validate($_POST['email']);
        $number = validate($_POST['number']);
        $profile = validate($_POST['profile']) ?: '../assets/images/default_profile.png';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = validate($_POST['role']);
        $is_ban = (int)validate($_POST['is_ban']);

        if (!preg_match('/^09[0-9]{9}$/', $number)) {
            redirect('admin.php?' . http_build_query($_GET), 'Phone number must start with 09 and be 11 digits long.', 'danger');
        }

        if (!empty($password) && (strlen($password) < 8 || !preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password))) {
            redirect('admin.php?' . http_build_query($_GET), 'Password must be at least 8 characters long and contain at least one letter and one number.', 'danger');
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('admin.php?' . http_build_query($_GET), 'Email already exists.', 'danger');
        }
        $stmt->close();

        $stmt = $conn->prepare("SELECT id FROM users WHERE number = ? AND id != ?");
        $stmt->bind_param("si", $number, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            redirect('admin.php?' . http_build_query($_GET), 'Phone number already exists.', 'danger');
        }
        $stmt->close();

        if (!in_array($role, ['admin', 'registrar', 'cashier'])) {
            redirect('admin.php?' . http_build_query($_GET), 'Invalid role.', 'danger');
        }

        if (!in_array($is_ban, [0, 1])) {
            redirect('admin.php?' . http_build_query($_GET), 'Invalid status.', 'danger');
        }

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, number = ?, profile = ?, password = ?, role = ?, is_ban = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("sssssssii", $firstname, $lastname, $email, $number, $profile, $hashed_password, $role, $is_ban, $id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, number = ?, profile = ?, role = ?, is_ban = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssssii", $firstname, $lastname, $email, $number, $profile, $role, $is_ban, $id);
        }

        if ($stmt->execute()) {
            redirect('admin.php?' . http_build_query($_GET), 'Admin updated successfully.', 'success');
        } else {
            redirect('admin.php?' . http_build_query($_GET), 'Failed to update admin: ' . $stmt->error, 'danger');
        }
        $stmt->close();
        break;

    case 'delete':
        if (!isset($_POST['id']) || !is_numeric($_POST['id']) || (int)$_POST['id'] <= 0) {
            redirect('admin.php?' . http_build_query($_GET), 'Invalid admin ID.', 'danger');
        }

        $id = (int)validate($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            redirect('admin.php?' . http_build_query($_GET), 'Admin deleted successfully.', 'success');
        } else {
            redirect('admin.php?' . http_build_query($_GET), 'Failed to delete admin: ' . $stmt->error, 'danger');
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
            $response = ['status' => 400, 'message' => 'Invalid admin ID.'];
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
            $response = ['status' => 200, 'message' => 'Admin fetched successfully.', 'data' => $result->fetch_assoc()];
        } else {
            $response = ['status' => 404, 'message' => 'Admin not found.'];
        }

        $stmt->close();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;

    default:
        redirect('admin.php', 'Invalid action.', 'danger');
        break;
}
?>