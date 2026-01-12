<?php
// config/database.php
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'mz_profit_bd';
$port = getenv('DB_PORT') ?: 3306;

// Create MySQLi connection
$conn = new mysqli($host, $user, $password, $database, $port);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed', 'message' => $conn->connect_error]));
}

$conn->set_charset("utf8mb4");
