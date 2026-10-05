<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require 'config/rate_limit.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/* ---- Rate limit: 20 love toggles / minute per session ---- */
if (!rate_limit_allow('love_user', 20, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests. Slow down.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid session']);
    exit;
}

$loved_id = (int)($_POST['user_id'] ?? 0);

if ($loved_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid user']);
    exit;
}
if ($loved_id === (int)$_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'You cannot love your own account']);
    exit;
}

$stmt = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
$stmt->execute([$loved_id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT love_id FROM user_loves WHERE lover_id = ? AND loved_id = ?");
    $stmt->execute([$_SESSION['user_id'], $loved_id]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $pdo->prepare("DELETE FROM user_loves WHERE love_id = ?")->execute([$existing]);
        $has_loved = false;
    } else {
        $pdo->prepare("INSERT INTO user_loves (lover_id, loved_id) VALUES (?, ?)")
            ->execute([$_SESSION['user_id'], $loved_id]);
        $has_loved = true;
    }

    $stats = get_user_love_stats($pdo, $loved_id);

    echo json_encode([
        'ok'        => true,
        'has_loved' => $has_loved,
        'received'  => $stats['received'],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}