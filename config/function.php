<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'dbcon.php';

// Input Validation
function validate($inputData) {
    if (is_array($inputData)) {
        return array_map('trim', $inputData);
    }
    return trim($inputData);
}

// Session Management
function logoutSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_unset();
    session_destroy();
    redirect('index.php', 'You have been logged out successfully.');
}

// Redirection
function redirect($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit();
}

// Alert Messages
function alertMessage() {
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
function checkParamId($paramType) {
    if (isset($_GET[$paramType]) && !empty($_GET[$paramType])) {
        return trim($_GET[$paramType]);
    }
    return 'No id found';
}

// Database Queries (Secure with Prepared Statements)
function getAll($tableName) {
    global $conn;
    $table = trim($tableName);

    $stmt = $conn->prepare("SELECT * FROM `$table`");
    $stmt->execute();
    return $stmt->get_result();
}

function getById($tableName, $id) {
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

function deleteQuery($tableName, $id) {
    global $conn;
    $table = trim($tableName);
    $id = trim($id);

    $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id); // 'i' for integer
    return $stmt->execute();
}

// Close connection (optional, can be called at script end)
function closeConnection() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
}

