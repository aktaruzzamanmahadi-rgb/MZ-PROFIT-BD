<?php
// index.php - Main API Entry Point
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/database.php';

// Route handler
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/server/', '', $path);
$parts = explode('/', trim($path, '/'));

if ($parts[0] === 'api') {
    $resource = $parts[1] ?? '';
    
    switch ($resource) {
        case 'health':
            echo json_encode(['status' => 'API is running', 'timestamp' => date('c')]);
            break;
        case 'auth':
            require __DIR__ . '/routes/auth.php';
            break;
        case 'users':
            require __DIR__ . '/routes/users.php';
            break;
        case 'ads':
            require __DIR__ . '/routes/ads.php';
            break;
        case 'transactions':
            require __DIR__ . '/routes/transactions.php';
            break;
        case 'campaigns':
            require __DIR__ . '/routes/campaigns.php';
            break;
        case 'admin':
            require __DIR__ . '/routes/admin.php';
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'API not found']);
}
