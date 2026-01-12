<?php
// routes/auth.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing required fields']));
    }

    $username = $data['username'];
    $email = $data['email'];
    $password = $data['password'];
    $phone = $data['phone'] ?? '';
    $role = $data['role'] ?? 'investor';

    // Check if user exists
    $result = $conn->query("SELECT user_id FROM users WHERE email = '$email'");
    if ($result->num_rows > 0) {
        http_response_code(400);
        die(json_encode(['error' => 'User already exists']));
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, phone, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $email, $hashedPassword, $phone, $role);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            'message' => 'User registered successfully',
            'user_id' => $conn->insert_id,
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed', 'message' => $stmt->error]);
    }
    $stmt->close();

} else if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing email or password']));
    }

    $email = $data['email'];
    $password = $data['password'];

    $result = $conn->query("SELECT * FROM users WHERE email = '$email'");
    if ($result->num_rows === 0) {
        http_response_code(401);
        die(json_encode(['error' => 'Invalid credentials']));
    }

    $user = $result->fetch_assoc();
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Invalid credentials']));
    }

    $token = generateJWT($user['user_id'], $user['email'], $user['role']);

    echo json_encode([
        'message' => 'Login successful',
        'token' => $token,
        'user' => [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'wallet_balance' => $user['wallet_balance']
        ]
    ]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
}
