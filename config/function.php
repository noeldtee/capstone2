<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'dbcon.php';

// Input Validation
function validate($inputData)
{
    if (is_array($inputData)) {
        return array_map('trim', $inputData);
    }
    return trim($inputData);
}

// Session Management
function logoutSession()
{
    global $conn; // Access global $conn

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Clear remember_email cookie if it exists
    if (isset($_COOKIE['remember_email'])) {
        setcookie('remember_email', '', time() - 3600, '/', '', false, true);
    }

    // Clear session
    $_SESSION = [];
    session_unset();
    session_destroy();

    redirect('index.php', 'Logged out successfully.', 'success');
}

// Redirection
function redirect($url, $message, $type = 'success')
{
    error_log("Redirecting to: $url with message: $message ($type)");
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

// Alert Messages
function alertMessage()
{
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message_type'] ?? 'success';
        echo '<div class="alert alert-' . htmlspecialchars($type) . '">
            <h4>' . htmlspecialchars($_SESSION['message']) . '</h4>
        </div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Parameter ID Check
function checkParamId($paramType)
{
    if (isset($_GET[$paramType]) && !empty($_GET[$paramType])) {
        return trim($_GET[$paramType]);
    }
    return 'No id found';
}

// Database Queries (Secure with Prepared Statements)
function getAll($tableName)
{
    global $conn;
    $table = trim($tableName);

    $stmt = $conn->prepare("SELECT * FROM `$table`");
    $stmt->execute();
    return $stmt->get_result();
}

function getById($tableName, $id)
{
    global $conn;
    $table = trim($tableName);
    $id = trim($id);

    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id); // 'i' for integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            return [
                'status' => 200,
                'message' => 'Fetched Data Successfully',
                'data' => $row
            ];
        } else {
            return [
                'status' => 404,
                'message' => 'No Data Record'
            ];
        }
    } else {
        error_log("Database error in getById: " . $conn->error);
        return [
            'status' => 500,
            'message' => 'Something went wrong'
        ];
    }
}

function deleteQuery($tableName, $id)
{
    global $conn;
    $table = trim($tableName);
    $id = trim($id);

    $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id); // 'i' for integer
    return $stmt->execute();
}

// Close connection (optional, can be called at script end)
function closeConnection()
{
    global $conn;
    if ($conn) {
        $conn->close();
    }
}

function logAction($conn, $action_type, $target, $details, $ip_address = null)
{
    // Ensure the user ID is set (e.g., from session)
    $performed_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // If no user is logged in, skip logging
    if ($performed_by === null || $performed_by === 0) {
        error_log("Cannot log action: No valid user ID. Action: $action_type, Target: $target");
        return false; // Indicate failure
    }

    // Verify that the user ID exists in the users table
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    if (!$stmt) {
        error_log("Failed to prepare statement in logAction (user check): " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $performed_by);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        error_log("Cannot log action: User ID $performed_by does not exist. Action: $action_type, Target: $target");
        $stmt->close();
        return false;
    }
    $stmt->close();

    // Add IP address to details if provided
    if ($ip_address) {
        $details .= " (IP: $ip_address)";
    }

    // Log the action
    $stmt = $conn->prepare("INSERT INTO action_logs (action_type, performed_by, target, details, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) {
        error_log("Failed to prepare statement in logAction: " . $conn->error);
        return false;
    }
    $stmt->bind_param("siss", $action_type, $performed_by, $target, $details);
    $success = $stmt->execute();
    if (!$success) {
        error_log("Failed to execute logAction: " . $stmt->error);
    }
    $stmt->close();

    return $success; // Return true on success, false on failure
}