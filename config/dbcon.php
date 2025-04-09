<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables (optional for production)
//require 'vendor/autoload.php'; // If using phpdotenv
//$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
//$dotenv->load();

define('DB_SERVER', $_ENV['DB_SERVER'] ?? 'localhost');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'root');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
define('DB_DATABASE', $_ENV['DB_DATABASE'] ?? 'bpcregistrar');

$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

if (!$conn) {
    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax) {
        // For AJAX requests, return a JSON error response
        $response = [
            'status' => 500,
            'message' => 'Failed to connect to database: ' . mysqli_connect_error()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        // For non-AJAX requests, redirect to error.php
        $_SESSION['error'] = "Failed to connect to database: " . mysqli_connect_error();
        header("Location: error.php");
        exit();
    }
}

mysqli_set_charset($conn, 'utf8mb4');