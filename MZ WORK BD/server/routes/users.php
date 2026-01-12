<?php
// routes/users.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = requireAuth();
$userId = $user['user_id'];

if ($method === 'GET' && $action === 'profile') {
    $result = $conn->query("SELECT user_id, username, email, phone, role, wallet_balance, nid_verified, account_status, created_at FROM users WHERE user_id = $userId");
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['error' => 'User not found']));
    }

    $profile = $result->fetch_assoc();
    echo json_encode($profile);

} else if ($method === 'PUT' && $action === 'profile') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';

    $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE user_id = ?");
    $stmt->bind_param("si", $phone, $userId);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update profile']);
    }
    $stmt->close();

} else if ($method === 'POST' && $action === 'verify-nid') {
    $data = json_decode(file_get_contents('php://input'), true);
    $nid = $data['nid'] ?? '';

    if (!$nid) {
        http_response_code(400);
        die(json_encode(['error' => 'NID is required']));
    }

    $stmt = $conn->prepare("UPDATE users SET nid = ? WHERE user_id = ?");
    $stmt->bind_param("si", $nid, $userId);

    if ($stmt->execute()) {
        echo json_encode([
            'message' => 'NID submitted for verification. Admin will review within 24-48 hours.',
            'status' => 'pending'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit NID']);
    }
    $stmt->close();

} else if ($method === 'GET' && $action === 'wallet') {
    $result = $conn->query("SELECT wallet_balance FROM users WHERE user_id = $userId");
    $wallet = $result->fetch_assoc();

    echo json_encode(['wallet_balance' => $wallet['wallet_balance']]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
}
