<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();

$handle = normalize_handle($_GET['u'] ?? '');

if ($handle === '') {
    header("Location: tutorials.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT user_id, full_name, username, bio, avatar_path, banner_path, role
    FROM users WHERE username = ?
");
$stmt->execute([$handle]);
$profile = $stmt->fetch();

if (!$profile) {
    header("Location: 404.php");
    exit;
}

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type    = $_SESSION['flash_type']    ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$is_self = ((int)$profile['user_id'] === (int)$_SESSION['user_id']);

$tut_stmt = $pdo->prepare("
    SELECT * FROM tutorials
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$tut_stmt->execute([$profile['user_id']]);
$tutorials = $tut_stmt->fetchAll();

$display_name = !empty($profile['full_name']) ? $profile['full_name'] : $profile['username'];
$avatar       = resolve_avatar($profile['avatar_path'] ?? null);
$banner       = resolve_banner($profile['banner_path'] ?? null);
$bio          = trim($profile['bio'] ?? '');

$love_stats = get_user_love_stats($pdo, (int)$profile['user_id']);
$has_loved  = $is_self ? false : user_has_loved($pdo, (int)$_SESSION['user_id'], (int)$profile['user_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= safe($display_name) ?> (@<?= safe($profile['username']) ?>) – Alight Creators</title>
  <meta name="description" content="<?= safe(mb_substr($bio, 0, 150)) ?>" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="hero-glow-bg"></div>

      <div class="profile-discord-wrapper">

        <?php if ($flash_message): ?>
          <div class="toast toast-static <?= $flash_type === 'error' ? 'toast-error' : 'toast-success' ?> show" style="margin-bottom:1.5rem;">
            <?= safe($flash_message) ?>
          </div>
        <?php endif; ?>

        <div class="profile-card-discord">

          <div class="profile-banner"<?= $banner ? ' style="background-image: url(' . safe($banner) . ');"' : '' ?>></div>

          <div class="profile-avatar-block">
            <div class="profile-avatar-discord">
              <img src="<?= safe($avatar) ?>" alt="<?= safe($display_name) ?>" />
            </div>
          </div>

          <div class="profile-info-discord">

            <div class="profile-username-row">
              <h1 class="profile-username"><?= safe($display_name) ?></h1>

              <?php if ($is_self): ?>
                <span class="profile-loves-pill" title="Loves received">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                  </svg>
                  <span><?= $love_stats['received'] ?></span>
                </span>
              <?php else: ?>
                <button type="button"
                        id="love-btn"
                        class="profile-loves-pill loves-interactive <?= $has_loved ? 'loved' : '' ?>"
                        data-user-id="<?= (int)$profile['user_id'] ?>"
                        data-csrf="<?= safe(csrf_token()) ?>"
                        title="<?= $has_loved ? 'Click to unlove' : 'Love this creator' ?>"
                        aria-pressed="<?= $has_loved ? 'true' : 'false' ?>">
                  <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                  </svg>
                  <span id="love-count"><?= $love_stats['received'] ?></span>
                </button>
              <?php endif; ?>
            </div>

            <div class="profile-handle-row">
              <span class="profile-handle">@<?= safe($profile['username']) ?></span>
              <?php if (($profile['role'] ?? '') === 'admin'): ?>
                <span class="profile-badge admin">Admin</span>
              <?php else: ?>
                <span class="profile-badge">Member</span>
              <?php endif; ?>
            </div>

          </div>

          <?php if ($bio !== ''): ?>
            <div class="profile-section">
              <h3>About Me</h3>
              <p><?= safe($bio) ?></p>
            </div>
          <?php endif; ?>

          <?php if ($is_self): ?>
            <div class="profile-menu">
              <a href="profile.php" class="profile-menu-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
                <span class="menu-label">Edit Profile</span>
              </a>
            </div>
          <?php endif; ?>

        </div>

        <div class="profile-tutorials-section">
          <div class="profile-tutorials-header">
            <h2>Published Tutorials</h2>
          </div>

          <?php if (count($tutorials) > 0): ?>
            <div class="tutorials-grid">
              <?php foreach ($tutorials as $tut): ?>
                <a class="tutorial-card" href="tutorial-detail.php?id=<?= (int)$tut['tutorial_id'] ?>">
                  <div class="tutorial-thumb" style="<?= !empty($tut['thumbnail_path']) ? 'background-image:url(' . safe($tut['thumbnail_path']) . ');background-size:cover;background-position:center;' : '' ?>"></div>
                  <div class="tutorial-info">
                    <h3><?= safe($tut['title']) ?></h3>
                    <span class="badge <?= badge_for($tut['category']) ?>"><?= safe($tut['category']) ?></span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted">No tutorials published yet.</p>
          <?php endif; ?>
        </div>

      </div>
    </main>

    <script src="assets/js/script.js"></script>
    <script src="assets/js/user.js"></script>
  </div><!-- /.app -->
</body>
</html>