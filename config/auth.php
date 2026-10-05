<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function require_guest(): void {
    if (isset($_SESSION['user_id'])) {
        header("Location: home.php");
        exit;
    }
}
?>