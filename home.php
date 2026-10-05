<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/functions.php';
require_login();

$welcome = !empty($_SESSION['just_registered']);
unset($_SESSION['just_registered']);

/* ============================================================
   RECOMMENDED — Top-rated tutorials (quality + clarity average).
   Falls back to views when a tutorial has no ratings yet, so the
   section never appears empty on a fresh install.

   NOTE: t.created_at is included in GROUP BY to satisfy
   ONLY_FULL_GROUP_BY (MySQL 5.7+ / MariaDB 10.2+ default).
   ============================================================ */
try {
    $stmt = $pdo->query("
        SELECT
            t.tutorial_id,
            t.title,
            t.category,
            t.thumbnail_path,
            t.views,
            t.created_at,
            COALESCE(AVG(r.quality_rating), 0) AS avg_quality,
            COALESCE(AVG(r.clarity_rating), 0) AS avg_clarity,
            COALESCE((AVG(r.quality_rating) + AVG(r.clarity_rating)) / 2, 0) AS avg_score,
            COUNT(r.rating_id) AS rating_count
        FROM tutorials t
        LEFT JOIN tutorial_ratings r ON t.tutorial_id = r.tutorial_id
        GROUP BY
            t.tutorial_id, t.title, t.category, t.thumbnail_path,
            t.views, t.created_at
        ORDER BY
            avg_score DESC,
            rating_count DESC,
            t.views DESC,
            t.created_at DESC
        LIMIT 5
    ");
    $recommended_tutorials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('home.php recommended query: ' . $e->getMessage());
    $recommended_tutorials = [];
}

/* ============================================================
   LATEST — Newest uploads by creation date.
   ============================================================ */
try {
    $stmt = $pdo->query("
        SELECT * FROM tutorials
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $latest_tutorials = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('home.php latest query: ' . $e->getMessage());
    $latest_tutorials = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Home – Alight Creators</title>
  <meta name="description" content="Browse recommended Alight Motion tutorials and get learning fast." />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
    <?php if ($welcome): ?>
      <div class="toast welcome" id="welcome-toast">🎉 Welcome to Alight Creators! Start exploring tutorials below.</div>
    <?php endif; ?>

    <div class="hero-glow-bg"></div>

      <div class="home-layout">
        <div class="sidebar" style="visibility: hidden; pointer-events: none;"></div>

        <section class="content page-content">

          <section class="page-hero">
            <div class="page-hero-inner">
              <div class="page-hero-text">
                <h1>Master <span class="text-primary">Alight Motion</span></h1>
                <p>Step-by-Step Video Editing Tutorials</p>
              </div>
              <div class="page-hero-image">
                <img src="assets/images/am-mascot.jpg" alt="AM Mascot" />
              </div>
            </div>
          </section>

          <div class="page-scroll-area">

            <section class="home-section-gap">
              <h2 class="section-title">Recommended Tutorials</h2>

              <?php if (count($recommended_tutorials) > 0): ?>
                <div class="carousel" data-carousel>
                  <button type="button"
                          class="carousel-nav carousel-prev"
                          data-carousel-prev
                          aria-label="Scroll to previous tutorials">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="15 18 9 12 15 6"/>
                    </svg>
                  </button>

                  <div class="carousel-track" data-carousel-track>
                    <?php foreach ($recommended_tutorials as $tut): ?>
                      <a class="tutorial-card" href="tutorial-detail.php?id=<?= (int)$tut['tutorial_id'] ?>">
                        <div class="tutorial-thumb" style="<?= !empty($tut['thumbnail_path']) ? 'background-image:url(' . safe($tut['thumbnail_path']) . ');background-size:cover;background-position:center;' : '' ?>"></div>
                        <div class="tutorial-info">
                          <h3><?= safe($tut['title']) ?></h3>
                          <span class="badge <?= badge_for($tut['category']) ?>"><?= safe($tut['category']) ?></span>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </div>

                  <button type="button"
                          class="carousel-nav carousel-next"
                          data-carousel-next
                          aria-label="Scroll to next tutorials">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="9 18 15 12 9 6"/>
                    </svg>
                  </button>
                </div>
              <?php else: ?>
                <p class="text-muted">No recommended tutorials yet.</p>
              <?php endif; ?>
            </section>

            <section>
              <h2 class="section-title">Latest Tutorials</h2>

              <?php if (count($latest_tutorials) > 0): ?>
                <div class="carousel" data-carousel>
                  <button type="button"
                          class="carousel-nav carousel-prev"
                          data-carousel-prev
                          aria-label="Scroll to previous tutorials">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="15 18 9 12 15 6"/>
                    </svg>
                  </button>

                  <div class="carousel-track" data-carousel-track>
                    <?php foreach ($latest_tutorials as $tut): ?>
                      <a class="tutorial-card" href="tutorial-detail.php?id=<?= (int)$tut['tutorial_id'] ?>">
                        <div class="tutorial-thumb" style="<?= !empty($tut['thumbnail_path']) ? 'background-image:url(' . safe($tut['thumbnail_path']) . ');background-size:cover;background-position:center;' : '' ?>"></div>
                        <div class="tutorial-info">
                          <h3><?= safe($tut['title']) ?></h3>
                          <span class="badge <?= badge_for($tut['category']) ?>"><?= safe($tut['category']) ?></span>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </div>

                  <button type="button"
                          class="carousel-nav carousel-next"
                          data-carousel-next
                          aria-label="Scroll to next tutorials">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <polyline points="9 18 15 12 9 6"/>
                    </svg>
                  </button>
                </div>
              <?php else: ?>
                <p class="text-muted">No recent tutorials yet.</p>
              <?php endif; ?>
            </section>

          </div>
        </section>

        <aside class="right-panel page-content">
          <?php include 'includes/popular-tutorials.php'; ?>
        </aside>
      </div>
    </main>

    <script src="assets/js/script.js"></script>
    <script src="assets/js/home.js"></script>
    <script src="assets/js/home-carousel.js"></script>
  </div><!-- /.app -->
</body>
</html>