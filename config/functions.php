<?php

/* ============================================================
   UPLOAD HELPERS
   ============================================================ */

const DEFAULT_AVATAR = 'assets/images/default-avatar.jpg';

function upload_file($file, string $dir, array $allowedExts, int $maxBytes, string $prefix): ?string
{
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('File is too large.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('Invalid file type: .' . $ext);
    }

    $allowedMimeMap = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'mp4'  => ['video/mp4', 'application/mp4'],
        'webm' => ['video/webm'],
        'ogg'  => ['video/ogg', 'application/ogg'],
        'mov'  => ['video/quicktime', 'video/mov'],
    ];

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $expected = $allowedMimeMap[$ext] ?? [];
        if (!empty($expected) && !in_array($realMime, $expected, true)) {
            throw new RuntimeException(
                "File content doesn't match its extension (got {$realMime})."
            );
        }
    }

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Upload folder could not be created.');
    }

    $newName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = rtrim($dir, '/') . '/' . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not move uploaded file.');
    }
    return $dest;
}

/* ============================================================
   AVATAR / BANNER FALLBACKS
   ============================================================ */

function resolve_avatar(?string $path): string
{
    if (empty($path)) {
        return DEFAULT_AVATAR;
    }
    if (!is_file(__DIR__ . '/../' . ltrim($path, '/'))) {
        return DEFAULT_AVATAR;
    }
    return $path;
}

function resolve_banner(?string $path): string
{
    if (empty($path)) {
        return '';
    }
    if (!is_file(__DIR__ . '/../' . ltrim($path, '/'))) {
        return '';
    }
    return $path;
}

/* ============================================================
   STRING / URL SANITIZATION
   ============================================================ */

function safe(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalize_handle(?string $raw): string
{
    $h = strtolower(trim((string)$raw));
    return preg_replace('/[^a-z0-9_]/', '_', $h);
}

function sanitize_url(?string $url): ?string
{
    $url = trim((string)$url);
    if ($url === '') return null;

    if (!filter_var($url, FILTER_VALIDATE_URL)) return null;

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) return null;

    return $url;
}

/**
 * Returns a cache-busting version string for the main stylesheet.
 * Uses the file's mtime so the browser only re-downloads when the
 * CSS actually changes — not on every single request.
 */
function css_version(): string
{
    $file = __DIR__ . '/../assets/css/styles.css';
    return is_file($file) ? (string)filemtime($file) : '1';
}

/* ============================================================
   VIDEO EMBED
   ============================================================ */

/**
 * Convert a YouTube URL to its embeddable form.
 * Supports:
 *   https://www.youtube.com/watch?v=VIDEO_ID
 *   https://youtube.com/embed/VIDEO_ID
 *   https://youtu.be/VIDEO_ID
 *   https://www.youtube.com/shorts/VIDEO_ID
 */
function youtube_embed(?string $url): ?string
{
    if (!$url) return null;
    if (preg_match(
        '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~',
        $url,
        $m
    )) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return null;
}

/* ============================================================
   BADGES
   ============================================================ */

function badge_for(?string $cat): string {
    $map = [
        'Beginner'      => 'badge-beginner',
        'Intermediate'  => 'badge-intermediate',
        'Advanced'      => 'badge-advanced',
        'Tips & Tricks' => 'badge-tip',
    ];
    return $map[$cat] ?? 'badge-tip';
}

/* ============================================================
   VALIDATION
   ============================================================ */

function validate_birthdate(string $value): ?string
{
    if ($value === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if (!$d || $d->format('Y-m-d') !== $value) return null;

    $now = new DateTime();
    if ($d > $now) return null;

    $age = $now->diff($d)->y;
    if ($age < 13 || $age > 120) return null;

    return $value;
}

/* ============================================================
   RATINGS
   ============================================================ */

function get_tutorial_rating_stats(PDO $pdo, int $tutorial_id): array
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            AVG(quality_rating) AS avg_quality,
            AVG(clarity_rating) AS avg_clarity
        FROM tutorial_ratings
        WHERE tutorial_id = ?
    ");
    $stmt->execute([$tutorial_id]);
    $row = $stmt->fetch() ?: [];

    return [
        'total'       => (int)($row['total'] ?? 0),
        'avg_quality' => isset($row['avg_quality']) && $row['avg_quality'] !== null
                            ? round((float)$row['avg_quality'], 1) : 0.0,
        'avg_clarity' => isset($row['avg_clarity']) && $row['avg_clarity'] !== null
                            ? round((float)$row['avg_clarity'], 1) : 0.0,
    ];
}

function get_user_rating(PDO $pdo, int $tutorial_id, int $user_id): ?array
{
    $stmt = $pdo->prepare("
        SELECT quality_rating, clarity_rating
        FROM tutorial_ratings
        WHERE tutorial_id = ? AND user_id = ?
    ");
    $stmt->execute([$tutorial_id, $user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ============================================================
   LOVES
   ============================================================ */

function get_user_love_stats(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_loves WHERE loved_id = ?");
    $stmt->execute([$user_id]);
    $received = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_loves WHERE lover_id = ?");
    $stmt->execute([$user_id]);
    $given = (int)$stmt->fetchColumn();

    return ['received' => $received, 'given' => $given];
}

function user_has_loved(PDO $pdo, int $lover_id, int $loved_id): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM user_loves WHERE lover_id = ? AND loved_id = ? LIMIT 1");
    $stmt->execute([$lover_id, $loved_id]);
    return (bool)$stmt->fetchColumn();
}

/* ============================================================
   POPULAR TUTORIALS
   ============================================================ */

function get_popular_tutorials(PDO $pdo, int $limit = 6, ?int $exclude_id = null): array
{
    $limit = max(1, min(20, $limit));

    $sql    = "SELECT tutorial_id, title, category, views FROM tutorials";
    $params = [];

    if ($exclude_id !== null && $exclude_id > 0) {
        $sql .= " WHERE tutorial_id != ?";
        $params[] = $exclude_id;
    }

    $sql .= " ORDER BY views DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* ============================================================
   AUTHORIZATION
   ============================================================ */

/**
 * True when the current session belongs to an admin user.
 */
function is_admin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * True when the current user may manage (edit/delete) the given tutorial.
 * Owner OR admin.
 */
function can_manage_tutorial(array $tutorial): bool
{
    if (!isset($_SESSION['user_id'])) return false;
    if (is_admin()) return true;
    return (int)$tutorial['user_id'] === (int)$_SESSION['user_id'];
}

/* ============================================================
   ADMIN GATE
   ============================================================ */

function require_admin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    if (!is_admin()) {
        $_SESSION['flash_message'] = "You don't have permission to access that page.";
        $_SESSION['flash_type']    = "error";
        header("Location: home.php");
        exit;
    }
}

/* ============================================================
   PASSWORD RESET
   ============================================================ */

/**
 * Generate a fresh reset token for a user.
 * Returns the plain token (to embed in the link), not the hash.
 * Invalidates any earlier unused tokens for the same user.
 */
function create_password_reset(PDO $pdo, int $user_id, int $ttl_minutes = 15): string
{
    // Invalidate older unused tokens
    $pdo->prepare("
        DELETE FROM password_resets
        WHERE user_id = ? AND used_at IS NULL
    ")->execute([$user_id]);

    // Plain token goes to the user; hash goes to the DB
    $token      = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = (new DateTime())->modify("+{$ttl_minutes} minutes")->format('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (?, ?, ?)
    ")->execute([$user_id, $token_hash, $expires_at]);

    return $token;
}

/**
 * Look up a valid reset row for the given plain token.
 * Returns ['reset_id' => int, 'user_id' => int] or null.
 */
function find_valid_reset(PDO $pdo, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT reset_id, user_id, expires_at
        FROM password_resets
        WHERE token_hash = ?
          AND used_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$token_hash]);
    $row = $stmt->fetch();

    if (!$row) return null;
    if (strtotime($row['expires_at']) < time()) return null;

    return ['reset_id' => (int)$row['reset_id'], 'user_id' => (int)$row['user_id']];
}

/**
 * Mark a reset row as used and update the user's password in one transaction.
 */
function consume_password_reset(PDO $pdo, int $reset_id, int $user_id, string $new_password): void
{
    $pdo->beginTransaction();

    try {
        $pdo->prepare("
            UPDATE password_resets
            SET used_at = NOW()
            WHERE reset_id = ? AND used_at IS NULL
        ")->execute([$reset_id]);

        $pdo->prepare("
            UPDATE users
            SET password = ?
            WHERE user_id = ?
        ")->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/* ============================================================
   FILE CLEANUP
   ============================================================ */

function delete_file_safe(?string $path): void
{
    if ($path && is_file($path)) {
        @unlink($path);
    }
}