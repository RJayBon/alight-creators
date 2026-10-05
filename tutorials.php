<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/functions.php';
require_login();

$current_category = $_GET['category'] ?? 'All';
$search_query     = trim($_GET['q'] ?? '');

/* Whitelist so bogus query strings don't produce empty pages */
$allowed_categories = ['All', 'Beginner', 'Intermediate', 'Advanced', 'Tips & Tricks'];
if (!in_array($current_category, $allowed_categories, true)) {
    $current_category = 'All';
}

$is_searching = ($search_query !== '');

/* ============================================================
   HEADER TEXT
   ============================================================ */
if ($is_searching) {
    $header_title = "Search Results";
    $header_desc  = 'Showing matches for "' . $search_query . '" across all categories.';
} else {
    $header_title = "All Tutorials";
    $header_desc  = "Browse our entire library of motion design guides.";

    if ($current_category === 'Beginner') {
        $header_title = "Beginner Tutorials";
        $header_desc  = "Start your motion design journey with these fundamental guides.";
    } elseif ($current_category === 'Intermediate') {
        $header_title = "Intermediate Tutorials";
        $header_desc  = "Level up your skills with more complex techniques and effects.";
    } elseif ($current_category === 'Advanced') {
        $header_title = "Advanced Tutorials";
        $header_desc  = "Master Alight Motion with professional-grade workflows.";
    } elseif ($current_category === 'Tips & Tricks') {
        $header_title = "Tips & Tricks";
        $header_desc  = "Quick hacks, shortcuts, and advice to speed up your editing.";
    }
}

/* ============================================================
   FETCH
   When searching, we ignore the category filter and search
   across every tutorial so the user never misses a match just
   because they had a category pre-selected.
   ============================================================ */
try {
    if ($is_searching) {
        $like = '%' . $search_query . '%';
        $stmt = $pdo->prepare("
            SELECT * FROM tutorials
            WHERE title       LIKE ?
               OR description LIKE ?
               OR category    LIKE ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$like, $like, $like]);
        $tutorials = $stmt->fetchAll();
    } elseif ($current_category === 'All') {
        $stmt = $pdo->query("SELECT * FROM tutorials ORDER BY created_at DESC");
        $tutorials = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tutorials WHERE category = ? ORDER BY created_at DESC");
        $stmt->execute([$current_category]);
        $tutorials = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $tutorials = [];
}

/* Category list reused by both the mobile chip row and the desktop sidebar */
$category_list = [
    'All'           => 'All',
    'Beginner'      => 'Beginners',
    'Intermediate'  => 'Intermediate',
    'Advanced'      => 'Advanced',
    'Tips & Tricks' => 'Tips & Tricks',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= safe($header_title) ?> – Alight Creators</title>
  <meta name="description" content="<?= safe($header_desc) ?>" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="hero-glow-bg"></div>

      <div class="tutorials-layout">

        <?php
          $sidebar_active_category = $is_searching ? '' : $current_category;
          include 'includes/sidebar-categories.php';
        ?>

        <section class="content page-content">

          <!-- ============================================================
               PAGE HEADER — title + search bar (pinned above the scroll area)
               ============================================================ -->
          <div class="page-header">
            <div class="page-header-text">
              <h1><?= safe($header_title) ?></h1>
              <p><?= safe($header_desc) ?></p>
            </div>

            <form method="GET"
                  action="tutorials.php"
                  class="page-header-search"
                  role="search"
                  data-no-loader="true">
              <input type="text"
                     name="q"
                     value="<?= safe($search_query) ?>"
                     placeholder="Search tutorials…"
                     autocomplete="off"
                     autocapitalize="none"
                     spellcheck="false"
                     aria-label="Search tutorials" />
              <button type="submit" aria-label="Search">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="11" cy="11" r="8"/>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <span>Search</span>
              </button>
            </form>
          </div>

          <?php if ($is_searching): ?>
            <div class="search-meta-bar">
              <span>
                Found <strong><?= count($tutorials) ?></strong>
                <?= count($tutorials) === 1 ? 'result' : 'results' ?>
                for "<strong><?= safe($search_query) ?></strong>"
              </span>
              <a href="tutorials.php" class="search-clear-link">Clear search</a>
            </div>
          <?php endif; ?>

          <!-- ============ Mobile-only category chips ============ -->
          <nav class="mobile-category-chips" aria-label="Filter by category">
            <?php foreach ($category_list as $value => $label): ?>
              <a href="tutorials.php?category=<?= urlencode($value) ?>"
                 class="mobile-category-chip <?= (!$is_searching && $current_category === $value) ? 'active' : '' ?>">
                <?= safe($label) ?>
              </a>
            <?php endforeach; ?>
          </nav>

          <div class="page-scroll-area">
            <div class="tutorials-grid">
              <?php if (count($tutorials) > 0): ?>
                <?php foreach ($tutorials as $tut): ?>
                  <a class="tutorial-card" href="tutorial-detail.php?id=<?= (int)$tut['tutorial_id'] ?>">
                    <div class="tutorial-thumb" style="<?= !empty($tut['thumbnail_path']) ? 'background-image:url(' . safe($tut['thumbnail_path']) . ');background-size:cover;background-position:center;' : '' ?>"></div>
                    <div class="tutorial-info">
                      <h3><?= safe($tut['title']) ?></h3>
                      <span class="badge <?= badge_for($tut['category']) ?>"><?= safe($tut['category']) ?></span>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <?php if ($is_searching): ?>
                  <div class="tutorials-empty">
                    <p><strong>No tutorials found for "<?= safe($search_query) ?>".</strong></p>
                    <p class="tutorials-empty-hint">Try a different keyword, or <a href="tutorials.php" class="text-primary link-underline">browse all tutorials</a>.</p>
                  </div>
                <?php else: ?>
                  <p class="text-muted">No tutorials found for this category yet!</p>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

        </section>

        <aside class="right-panel page-content">
          <?php include 'includes/popular-tutorials.php'; ?>
        </aside>
      </div>
    </main>

    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>