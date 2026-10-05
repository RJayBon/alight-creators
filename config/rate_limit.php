<?php
/**
 * Session-based rate limiter.
 */

function rate_limit_allow(string $key, int $max = 5, int $windowSec = 300): bool
{
    $now = time();
    if (!isset($_SESSION['rl']) || !is_array($_SESSION['rl'])) {
        $_SESSION['rl'] = [];
    }
    if (!isset($_SESSION['rl'][$key]) || !is_array($_SESSION['rl'][$key])) {
        $_SESSION['rl'][$key] = ['count' => 0, 'start' => $now];
    }

    $bucket = &$_SESSION['rl'][$key];
    if ($now - $bucket['start'] > $windowSec) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    $bucket['count']++;
    return $bucket['count'] <= $max;
}

function rate_limit_reset(string $key): void
{
    if (isset($_SESSION['rl'][$key])) unset($_SESSION['rl'][$key]);
}