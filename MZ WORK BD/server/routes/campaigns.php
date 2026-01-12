<?php
// routes/campaigns.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = requireAuth();
$userId = $user['user_id'];

if ($method === 'GET' && $action === 'list') {
    $result = $conn->query("SELECT c.campaign_id, c.title, c.description, c.target_amount, c.raised_amount, c.category, c.status, u.username FROM campaigns c JOIN users u ON c.company_id = u.user_id WHERE c.status = 'active' LIMIT 50");
    
    $campaigns = [];
    while ($campaign = $result->fetch_assoc()) {
        $campaigns[] = $campaign;
    }

    echo json_encode($campaigns);

} else if ($method === 'POST' && $action === 'invest') {
    $data = json_decode(file_get_contents('php://input'), true);
    $campaignId = $data['campaign_id'] ?? 0;
    $amount = $data['amount'] ?? 0;

    if (!$campaignId || !$amount || $amount <= 0) {
        http_response_code(400);
        die(json_encode(['error' => 'Invalid campaign_id or amount']));
    }

    // Check wallet balance
    $result = $conn->query("SELECT wallet_balance FROM users WHERE user_id = $userId");
    $user_data = $result->fetch_assoc();

    if ($user_data['wallet_balance'] < $amount) {
        http_response_code(400);
        die(json_encode(['error' => 'Insufficient wallet balance']));
    }

    // Create investment
    $stmt = $conn->prepare("INSERT INTO investments (investor_id, campaign_id, amount, status) VALUES (?, ?, ?, 'approved')");
    $stmt->bind_param("iid", $userId, $campaignId, $amount);

    if ($stmt->execute()) {
        $investmentId = $conn->insert_id;

        // Deduct from wallet
        $conn->query("UPDATE users SET wallet_balance = wallet_balance - $amount WHERE user_id = $userId");

        // Update campaign raised amount
        $conn->query("UPDATE campaigns SET raised_amount = raised_amount + $amount WHERE campaign_id = $campaignId");

        http_response_code(201);
        echo json_encode([
            'message' => 'Investment successful',
            'investment_id' => $investmentId,
            'amount' => $amount,
            'status' => 'approved'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Investment failed']);
    }
    $stmt->close();

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
}
