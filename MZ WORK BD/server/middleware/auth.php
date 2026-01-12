<?php
// middleware/auth.php

function getAuthToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $parts = explode(' ', $headers['Authorization']);
        return count($parts) === 2 ? $parts[1] : null;
    }
    return null;
}

function verifyToken($token) {
    $secret = getenv('JWT_SECRET') ?: 'your_super_secret_key_change_this';
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    $signature = $parts[2];

    // Verify expiry
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return null;
    }

    return $payload;
}

function requireAuth() {
    $token = getAuthToken();
    if (!$token) {
        http_response_code(401);
        die(json_encode(['error' => 'No token provided']));
    }

    $user = verifyToken($token);
    if (!$user) {
        http_response_code(401);
        die(json_encode(['error' => 'Invalid token']));
    }

    return $user;
}

function requireAdmin() {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        die(json_encode(['error' => 'Admin access required']));
    }
    return $user;
}

function generateJWT($userId, $email, $role) {
    $secret = getenv('JWT_SECRET') ?: 'your_super_secret_key_change_this';
    $expiry = getenv('JWT_EXPIRY') ?: '7d';
    
    // Calculate expiry timestamp
    $expireTime = time() + (7 * 24 * 60 * 60); // 7 days default

    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'user_id' => $userId,
        'email' => $email,
        'role' => $role,
        'iat' => time(),
        'exp' => $expireTime
    ]);

    $base64UrlHeader = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

    $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $secret, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
}
