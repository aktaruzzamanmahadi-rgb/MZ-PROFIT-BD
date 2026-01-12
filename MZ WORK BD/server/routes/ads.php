<?php
// routes/ads.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user = requireAuth();
$userId = $user['user_id'];

if ($method === 'GET' && $action === 'list') {
    // Get today's click count
    $today = date('Y-m-d');
    $result = $conn->query("SELECT clicks_today FROM daily_ad_tracking WHERE user_id = $userId AND ad_date = '$today'");
    $clicksToday = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $clicksToday = $row['clicks_today'];
    }

    $canClickMore = $clicksToday < 5;

    // Get active ads
    $ads_result = $conn->query("SELECT ad_id, company_id, title, description, target_url, reward_value, clicks_count FROM ads WHERE status = 'active' LIMIT 20");
    $ads = [];
    while ($ad = $ads_result->fetch_assoc()) {
        $ads[] = $ad;
    }

    echo json_encode([
        'ads' => $ads,
        'daily_limit' => 5,
        'clicks_today' => $clicksToday,
        'can_click_more' => $canClickMore
    ]);

} else if ($method === 'POST' && $action === 'click-session') {
    $data = json_decode(file_get_contents('php://input'), true);
    $adId = $data['ad_id'] ?? null;

    if (!$adId) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing ad_id']));
    }

    $sessionId = "$userId-$adId-" . time();
    $today = date('Y-m-d');

    // Check daily limit
    $result = $conn->query("SELECT clicks_today FROM daily_ad_tracking WHERE user_id = $userId AND ad_date = '$today'");
    $clicksToday = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $clicksToday = $row['clicks_today'];
    }

    if ($clicksToday >= 5) {
        http_response_code(403);
        die(json_encode(['error' => 'Daily ad limit (5) reached']));
    }

    $expiresAt = (time() + 60) * 1000; // 60 seconds

    echo json_encode([
        'session_id' => $sessionId,
        'ad_id' => $adId,
        'timer_duration' => 30,
        'expires_at' => $expiresAt,
        'message' => 'Session started. Keep the tab open for 30 seconds.'
    ]);

} else if ($method === 'POST' && $action === 'click-complete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $sessionId = $data['session_id'] ?? null;
    $adId = $data['ad_id'] ?? null;

    if (!$sessionId || !$adId) {
        http_response_code(400);
        die(json_encode(['error' => 'Missing session_id or ad_id']));
    }

    $userIp = $_SERVER['REMOTE_ADDR'];
    $today = date('Y-m-d');

    // Check daily limit again
    $result = $conn->query("SELECT clicks_today FROM daily_ad_tracking WHERE user_id = $userId AND ad_date = '$today'");
    $clicksToday = 0;
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $clicksToday = $row['clicks_today'];
    }

    if ($clicksToday >= 5) {
        http_response_code(403);
        die(json_encode(['error' => 'Daily limit reached']));
    }

    // Check if ad exists
    $ad_result = $conn->query("SELECT reward_value FROM ads WHERE ad_id = $adId AND status = 'active'");
    if ($ad_result->num_rows === 0) {
        http_response_code(404);
        die(json_encode(['error' => 'Ad not found or inactive']));
    }

    $ad = $ad_result->fetch_assoc();
    $rewardValue = $ad['reward_value'];

    // Record click
    $stmt = $conn->prepare("INSERT INTO click_logs (user_id, ad_id, session_id, server_completed, user_ip_address) VALUES (?, ?, ?, TRUE, ?)");
    $stmt->bind_param("iiss", $userId, $adId, $sessionId, $userIp);
    $stmt->execute();
    $stmt->close();

    // Update daily tracking
    $result = $conn->query("SELECT tracking_id FROM daily_ad_tracking WHERE user_id = $userId AND ad_date = '$today'");
    if ($result->num_rows > 0) {
        $conn->query("UPDATE daily_ad_tracking SET clicks_today = clicks_today + 1 WHERE user_id = $userId AND ad_date = '$today'");
    } else {
        $conn->query("INSERT INTO daily_ad_tracking (user_id, ad_date, clicks_today) VALUES ($userId, '$today', 1)");
    }

    // Update ad click count
    $conn->query("UPDATE ads SET clicks_count = clicks_count + 1 WHERE ad_id = $adId");

    // Calculate earning (30 clicks = 5 TK)
    $clicksAfter = $clicksToday + 1;
    $earning = intval($clicksAfter / 30) * 5;

    if ($earning > 0) {
        $conn->query("UPDATE users SET wallet_balance = wallet_balance + $earning WHERE user_id = $userId");
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, trx_type, payment_method, status) VALUES (?, ?, 'earning', 'system', 'approved')");
        $stmt->bind_param("id", $userId, $earning);
        $stmt->execute();
        $stmt->close();
    }

    // Update last IP
    $conn->query("UPDATE users SET last_ip_address = '$userIp' WHERE user_id = $userId");

    echo json_encode([
        'message' => 'Click recorded successfully',
        'reward_earned' => $rewardValue,
        'total_earned_today' => $earning,
        'clicks_today' => $clicksAfter
    ]);

} else {
    http_response_code(404);
    echo json_encode(['error' => 'Action not found']);
}
