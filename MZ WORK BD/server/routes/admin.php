<?php
// routes/admin.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$admin = requireAdmin();
$adminId = $admin['user_id'];

if ($method === 'POST' && $action === 'approve-transaction') {
    $data = json_decode(file_get_contents('php://input'), true);
    $trxId = $data['trx_id'] ?? 0;
    $adminNotes = $data['admin_notes'] ?? '';

    if (!$trxId) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing trx_id']));
    }

    // Get transaction
    $result = $conn->query("SELECT user_id, amount, trx_type, status FROM transactions WHERE trx_id = $trxId");
    if ($result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['error' => 'Transaction not found']));
    }

    $trx = $result->fetch_assoc();
    if ($trx['status'] !== 'pending') {
        http_response_code(400);
        die(json_encode(['error' => 'Transaction already processed']));
    }

    $trxUserId = $trx['user_id'];
    $trxAmount = $trx['amount'];
    $trxType = $trx['trx_type'];

    // Update transaction
    $stmt = $conn->prepare("UPDATE transactions SET status = 'approved', admin_notes = ?, verified_by = ?, verified_at = NOW() WHERE trx_id = ?");
    $stmt->bind_param("sii", $adminNotes, $adminId, $trxId);
    $stmt->execute();
    $stmt->close();

    // Update wallet based on transaction type
    if ($trxType === 'deposit') {
        $conn->query("UPDATE users SET wallet_balance = wallet_balance + $trxAmount WHERE user_id = $trxUserId");
    } else if ($trxType === 'withdrawal') {
        $conn->query("UPDATE users SET wallet_balance = wallet_balance - $trxAmount WHERE user_id = $trxUserId");
    }

    // Log admin action
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, target_user_id) VALUES (?, 'approve_transaction', ?, ?)");
    $details = "Approved transaction $trxId";
    $stmt->bind_param("isi", $adminId, $details, $trxUserId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['message' => 'Transaction approved', 'trx_id' => $trxId]);

} else if ($method === 'POST' && $action === 'verify-nid') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['user_id'] ?? 0;

    if (!$userId) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing user_id']));
    }

    $conn->query("UPDATE users SET nid_verified = TRUE WHERE user_id = $userId");

    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, target_user_id) VALUES (?, 'verify_nid', 'NID verified', ?)");
    $stmt->bind_param("ii", $adminId, $userId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['message' => 'NID verified', 'user_id' => $userId]);

} else if ($method === 'GET' && $action === 'pending') {
    // Get pending transactions
    $transactions_result = $conn->query("SELECT trx_id, user_id, amount, payment_method, payment_trx_id, created_at FROM transactions WHERE status = 'pending' ORDER BY created_at ASC");
    $pending_transactions = [];
    while ($trx = $transactions_result->fetch_assoc()) {
        $pending_transactions[] = $trx;
    }

    // Get pending NID verifications
    $nids_result = $conn->query("SELECT user_id, username, nid FROM users WHERE nid IS NOT NULL AND nid_verified = FALSE");
    $pending_nids = [];
    while ($nid = $nids_result->fetch_assoc()) {
        $pending_nids[] = $nid;
    }

    echo json_encode([
        'pending_transactions' => $pending_transactions,
        'pending_nid_verifications' => $pending_nids
    ]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
}
