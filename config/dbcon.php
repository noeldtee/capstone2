<?php
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
    $_SESSION['error'] = "Failed to connect to database: " . mysqli_connect_error();
    header("Location: error.php");
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');