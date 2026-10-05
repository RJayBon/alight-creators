<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header("Location: home.php");
    exit;
}

$tutorial_id = (int)($_POST['tutorial_id'] ?? 0);
$user_id     = (int)$_SESSION['user_id'];

if ($tutorial_id <= 0) {
    header("Location: home.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT user_id, thumbnail_path, guide_video_file, result_video_file
    FROM tutorials WHERE tutorial_id = ?
");
$stmt->execute([$tutorial_id]);
$tut = $stmt->fetch();

if (!$tut) {
    $_SESSION['flash_message'] = "Tutorial not found.";
    $_SESSION['flash_type']    = "error";
    header("Location: home.php");
    exit;
}

/* ---- Owner OR admin may delete ---- */
if (!can_manage_tutorial($tut)) {
    $_SESSION['flash_message'] = "You can only delete your own tutorials.";
    $_SESSION['flash_type']    = "error";
    header("Location: tutorial-detail.php?id=" . $tutorial_id);
    exit;
}

$is_own = ((int)$tut['user_id'] === $user_id);

/* ---- Delete DB row FIRST (cascade removes steps, views, ratings, resources) ----
   If this fails, the files on disk remain intact and the tutorial is still
   usable. Deleting files first would leave the row pointing at missing files,
   producing broken thumbnails and 404s for every future visitor. */
$pdo->prepare("DELETE FROM tutorials WHERE tutorial_id = ?")->execute([$tutorial_id]);

/* ---- Now it's safe to remove the uploaded files ---- */
foreach (['thumbnail_path', 'guide_video_file', 'result_video_file'] as $col) {
    delete_file_safe($tut[$col] ?? null);
}

/* ---- Flash + redirect ---- */
if ($is_own) {
    /* Author deleting own tutorial → their public profile */
    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $handle = $stmt->fetchColumn() ?: '';

    $_SESSION['flash_message'] = "Tutorial deleted successfully.";
    $_SESSION['flash_type']    = "success";
    header("Location: user.php?u=" . urlencode($handle));
} else {
    /* Admin deleting someone else's tutorial → home */
    $_SESSION['flash_message'] = "Tutorial deleted successfully (admin action).";
    $_SESSION['flash_type']    = "success";
    header("Location: home.php");
}
exit;