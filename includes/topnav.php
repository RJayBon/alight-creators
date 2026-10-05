<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/csrf.php';

$current_page = basename($_SERVER['PHP_SELF']);
$nav_is_logged_in = isset($_SESSION['user_id']);
$nav_user_name = "User";        // full display name (for title / alt)
$nav_user_initials = "U";       // short 2-letter tag for the button
$nav_avatar = DEFAULT_AVATAR;

/* ------------------------------------------------------------
   Build a 2-letter initials tag from a display name.
   • "Juan Delacruz"  → "JD"
   • "Juan"           → "JU"
   • "juan_dela_cruz" → "JU"  (single word, first two letters)
   • "A B C"          → "AB"  (first two words)
   • ""               → "U"

   Wrapped in function_exists() so the file can be safely
   included more than once without a redeclaration error.
   ------------------------------------------------------------ */
if (!function_exists('nav_initials')) {
    function nav_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return 'U';

        /* Split on whitespace, dots, underscores, and hyphens */
        $parts = preg_split('/[\s._\-]+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) return 'U';

        if (count($parts) >= 2) {
            /* Two or more words → first letter of first two words */
            return mb_strtoupper(
                mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1)
            );
        }

        /* Single word → first two letters */
        return mb_strtoupper(mb_substr($parts[0], 0, 2));
    }
}

if ($nav_is_logged_in) {
    try {
        $nav_stmt = $pdo->prepare("SELECT full_name, username, avatar_path FROM users WHERE user_id = ?");
        $nav_stmt->execute([$_SESSION['user_id']]);
        $nav_user = $nav_stmt->fetch();
        if ($nav_user) {
            $nav_user_name     = !empty($nav_user['full_name']) ? $nav_user['full_name'] : $nav_user['username'];
            $nav_user_initials = nav_initials($nav_user_name);
            $nav_avatar        = resolve_avatar($nav_user['avatar_path'] ?? null);
        }
    } catch (PDOException $e) { /* silent */ }
}
?>
<!-- ============ Global page loader ============ -->
<div class="page-loader" id="page-loader" aria-hidden="true">
  <div class="page-loader-ring"></div>
</div>

<!-- Fallback: if JS is disabled, hide the loader immediately -->
<noscript><style>#page-loader{display:none !important}</style></noscript>

<!-- ============ Top navigation progress bar ============ -->
<div class="top-progress" id="top-progress" aria-hidden="true"></div>

<header class="navbar">
  <a class="brand" href="index.php">
    <img src="assets/images/logo.svg" alt="Alight Creators logo" />
    Alight <span>Creators</span>
  </a>

  <?php if ($nav_is_logged_in): ?>
    <nav>
      <ul class="nav-links">
        <li><a href="home.php" class="<?= $current_page == 'home.php' ? 'active' : '' ?>">Home</a></li>
        <li><a href="tutorials.php" class="<?= $current_page == 'tutorials.php' ? 'active' : '' ?>">Tutorials</a></li>
        <li><a href="about.php" class="<?= $current_page == 'about.php' ? 'active' : '' ?>">About</a></li>
        <li><a href="contact.php" class="<?= $current_page == 'contact.php' ? 'active' : '' ?>">Contact</a></li>
      </ul>
    </nav>
  <?php endif; ?>

  <div class="nav-actions">
    <?php if ($nav_is_logged_in): ?>
      <a href="add-tutorial.php" class="btn-post">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span class="post-label">Post</span>
      </a>

      <div class="user-menu" id="user-menu">
        <button class="user-trigger"
                id="user-trigger"
                aria-label="Profile menu for <?= safe($nav_user_name) ?>"
                title="<?= safe($nav_user_name) ?>">
          <img src="<?= safe($nav_avatar) ?>"
               alt="<?= safe($nav_user_name) ?>"
               class="user-avatar" />
          <span class="user-initials"><?= safe($nav_user_initials) ?></span>
        </button>
        <div class="user-dropdown">
          <?php if (is_admin()): ?>
            <button type="button" class="admin-item" onclick="location.href='admin-users.php'">
              ⚙️ Manage Users
            </button>
            <button type="button" class="admin-item" onclick="location.href='admin-messages.php'">
              ✉️ Messages
            </button>
          <?php endif; ?>
          <button onclick="location.href='user.php?u=<?= urlencode($_SESSION['username'] ?? '') ?>'">👁 View Profile</button>
          <form method="POST" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="logout">⏻ Log Out</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <a class="btn-login" href="login.php">Login</a>
      <a class="btn-register" href="register.php">Register</a>
    <?php endif; ?>
  </div>
</header>