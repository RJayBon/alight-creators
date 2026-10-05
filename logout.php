<?php
require 'config/auth.php';
require 'config/csrf.php';
require 'config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header("Location: home.php");
    exit;
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
              $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}
session_destroy();

header("Location: login.php");
exit;