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

/* ---- Rate limit: 20 rating writes / minute per session ---- */
if (!rate_limit_allow('rate_tutorial', 20, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many requests. Slow down.']);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid session']);
    exit;
}

$tutorial_id = (int)($_POST['tutorial_id'] ?? 0);
$quality     = (int)($_POST['quality']     ?? 0);
$clarity     = (int)($_POST['clarity']     ?? 0);

if ($tutorial_id <= 0 || $quality < 1 || $quality > 5 || $clarity < 1 || $clarity > 5) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid rating values']);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM tutorials WHERE tutorial_id = ?");
$stmt->execute([$tutorial_id]);
$tut = $stmt->fetch();

if (!$tut) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Tutorial not found']);
    exit;
}
if ((int)$tut['user_id'] === (int)$_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'You cannot rate your own tutorial']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO tutorial_ratings (tutorial_id, user_id, quality_rating, clarity_rating)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            quality_rating = VALUES(quality_rating),
            clarity_rating = VALUES(clarity_rating)
    ");
    $stmt->execute([$tutorial_id, $_SESSION['user_id'], $quality, $clarity]);

    $stats = get_tutorial_rating_stats($pdo, $tutorial_id);

    echo json_encode([
        'ok'          => true,
        'stats'       => $stats,
        'your_rating' => ['quality' => $quality, 'clarity' => $clarity],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}