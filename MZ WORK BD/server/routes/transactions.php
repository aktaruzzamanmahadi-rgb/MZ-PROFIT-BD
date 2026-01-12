<?php
// routes/transactions.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = requireAuth();
$userId = $user['user_id'];

if ($method === 'POST' && $action === 'deposit') {
    $data = json_decode(file_get_contents('php://input'), true);
    $amount = $data['amount'] ?? 0;
    $paymentMethod = $data['payment_method'] ?? 'bkash';
    $paymentTrxId = $data['payment_trx_id'] ?? '';

    if (!$amount || $amount <= 0) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid amount']));
    }

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, trx_type, payment_method, payment_trx_id, status) VALUES (?, ?, 'deposit', ?, ?, 'pending')");
    $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $paymentTrxId);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Deposit request submitted. Admin will verify within 48-72 hours.',
            'trx_id' => $conn->insert_id,
            'status' => 'pending'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to submit deposit']);
    }
    $stmt->close();

} else if ($method === 'GET' && $action === 'list') {
    $result = $conn->query("SELECT trx_id, amount, trx_type, payment_method, status, created_at FROM transactions WHERE user_id = $userId ORDER BY created_at DESC LIMIT 50");
    $transactions = [];

    while ($trx = $result->fetch_assoc()) {
        $transactions[] = $trx;
    }

    echo json_encode($transactions);

} else if ($method === 'POST' && $action === 'withdraw') {
    $data = json_decode(file_get_contents('php://input'), true);
    $amount = $data['amount'] ?? 0;
    $paymentMethod = $data['payment_method'] ?? 'bkash';
    $paymentTrxId = $data['payment_trx_id'] ?? '';

    if (!$amount || $amount <= 0) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid amount']));
    }

    // Check wallet balance
    $result = $conn->query("SELECT wallet_balance FROM users WHERE user_id = $userId");
    $user_data = $result->fetch_assoc();

    if ($user_data['wallet_balance'] < $amount) {
        http_response_code(400);
        die(json_encode(['error' => 'Insufficient wallet balance']));
    }

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, trx_type, payment_method, payment_trx_id, status) VALUES (?, ?, 'withdrawal', ?, ?, 'pending')");
    $stmt->bind_param("idss", $userId, $amount, $paymentMethod, $paymentTrxId);

    if ($stmt->execute()) {
        echo json_encode([
            'message' => 'Withdrawal request submitted. Admin will process within 48 hours.',
            'trx_id' => $conn->insert_id,
            'status' => 'pending'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to request withdrawal']);
    }
    $stmt->close();

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
}
