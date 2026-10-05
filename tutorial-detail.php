<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: tutorials.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tutorials WHERE tutorial_id = ?");
$stmt->execute([$id]);
$tutorial = $stmt->fetch();

if (!$tutorial) {
    header("Location: 404.php");
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$is_author = ((int)$tutorial['user_id'] === $current_user_id);

if (!$is_author) {
    $viewCheck = $pdo->prepare("
        SELECT 1 FROM tutorial_views
        WHERE tutorial_id = ? AND user_id = ?
        LIMIT 1
    ");
    $viewCheck->execute([$id, $current_user_id]);

    if (!$viewCheck->fetchColumn()) {
        try {
            $ins = $pdo->prepare("INSERT IGNORE INTO tutorial_views (tutorial_id, user_id) VALUES (?, ?)");
            $ins->execute([$id, $current_user_id]);

            if ($ins->rowCount() === 1) {
                $pdo->prepare("UPDATE tutorials SET views = views + 1 WHERE tutorial_id = ?")
                    ->execute([$id]);
                $tutorial['views'] = (int)$tutorial['views'] + 1;
            }
        } catch (PDOException $e) {
            error_log('view counter race: ' . $e->getMessage());
        }
    }
}

$author = null;
if (!empty($tutorial['user_id'])) {
    $a_stmt = $pdo->prepare("SELECT full_name, username, avatar_path FROM users WHERE user_id = ?");
    $a_stmt->execute([$tutorial['user_id']]);
    $author = $a_stmt->fetch();
}

$stepStmt = $pdo->prepare("SELECT * FROM tutorial_steps WHERE tutorial_id = ? ORDER BY step_number ASC");
$stepStmt->execute([$id]);
$steps = $stepStmt->fetchAll();

$resStmt = $pdo->prepare("
    SELECT resource_type, resource_name, resource_url
    FROM tutorial_resources
    WHERE tutorial_id = ?
    ORDER BY resource_id ASC
");
$resStmt->execute([$id]);
$resources = $resStmt->fetchAll();

$embedUrl = youtube_embed($tutorial['guide_video_url'] ?? '');

$rating_stats = get_tutorial_rating_stats($pdo, $id);
$your_rating  = get_user_rating($pdo, $id, $current_user_id);

$has_result_file = !empty($tutorial['result_video_file']);
$has_result_url  = !empty($tutorial['result_video_url']);
$has_result      = $has_result_file || $has_result_url;

$result_embed = null;
if ($has_result_url) {
    $result_embed = youtube_embed($tutorial['result_video_url']);
}

/* ---- Build absolute URL for sharing ---- */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$share_url = $scheme . '://' . $host . $dir . '/tutorial-detail.php?id=' . (int)$tutorial['tutorial_id'];

/* ---- Build sidebar resources block ---- */
ob_start();
if (count($resources) > 0):
?>
  <div class="sidebar-resources-block">
    <h4 class="sidebar-resources-title">
      Downloads &amp; Assets
      <span class="sidebar-resources-count"><?= count($resources) ?></span>
    </h4>
    <?php foreach ($resources as $r):
      $is_preset = ($r['resource_type'] === 'preset');
      $icon      = $is_preset ? '🎁' : '📦';
    ?>
      <a href="<?= safe($r['resource_url']) ?>"
         target="_blank"
         rel="noopener noreferrer"
         class="sidebar-resource-item"
         title="Download <?= safe($r['resource_name']) ?>">
        <span class="sidebar-resource-icon" aria-hidden="true"><?= $icon ?></span>
        <span class="sidebar-resource-name"><?= safe($r['resource_name']) ?></span>
        <span class="sidebar-resource-dl" aria-hidden="true">↓</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php
endif;
$sidebar_extra_html = ob_get_clean();

/* Category list for mobile chip row */
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
  <title><?= safe($tutorial['title']) ?> – Alight Creators</title>
  <meta name="description" content="<?= safe($tutorial['description']) ?>" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="tutorials-layout">

        <?php include 'includes/sidebar-categories.php'; ?>

        <section class="content">

          <a href="tutorials.php" class="tut-back-btn" id="tut-back-btn">
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none"
                   stroke="currentColor" stroke-width="2.2"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
              </svg>
              <span>Back</span>
            </a>

          <nav class="mobile-category-chips" aria-label="Browse categories">
            <?php foreach ($category_list as $value => $label): ?>
              <a href="tutorials.php?category=<?= urlencode($value) ?>"
                 class="mobile-category-chip <?= $tutorial['category'] === $value ? 'active' : '' ?>">
                <?= safe($label) ?>
              </a>
            <?php endforeach; ?>
          </nav>

          <div class="tut-tag-row">
            <span class="tut-tag"><?= safe($tutorial['category']) ?></span>
            <span class="tut-tag-time"><?= (int)$tutorial['views'] ?> views</span>

            <?php if ($author): ?>
              <a href="user.php?u=<?= urlencode($author['username']) ?>" class="tut-author-link">
                <img src="<?= safe(resolve_avatar($author['avatar_path'] ?? null)) ?>"
                     alt="<?= safe($author['username']) ?>"
                     class="tut-author-avatar" />
                <span><?= safe($author['full_name'] ?: $author['username']) ?></span>
              </a>
            <?php endif; ?>
          </div>

          <div class="tut-title-row">
            <h1 class="tut-title"><?= safe($tutorial['title']) ?></h1>

            <div class="tut-owner-actions">

              <!-- ✅ SHARE (opens link-copy modal) -->
              <button type="button"
                      class="tut-action-btn tut-action-share"
                      id="share-btn"
                      data-share-url="<?= safe($share_url) ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="18" cy="5" r="3"/>
                  <circle cx="6" cy="12" r="3"/>
                  <circle cx="18" cy="19" r="3"/>
                  <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                  <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>
                Share
              </button>

              <?php if ($is_author || is_admin()): ?>
                <a href="edit-tutorial.php?id=<?= (int)$tutorial['tutorial_id'] ?>"
                   class="tut-action-btn tut-action-edit">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                       stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                  </svg>
                  Edit
                </a>

                <form method="POST" action="delete-tutorial.php"
                      onsubmit="return confirm('Delete this tutorial?\n\nThis cannot be undone. All steps, ratings, and uploaded files will be permanently removed.');"
                      style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tutorial_id" value="<?= (int)$tutorial['tutorial_id'] ?>" />
                  <button type="submit" class="tut-action-btn tut-action-delete">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18"/>
                      <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                      <line x1="10" y1="11" x2="10" y2="17"/>
                      <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                    Delete
                  </button>
                </form>
              <?php endif; ?>

            </div>
          </div>

          <p class="tut-desc"><?= nl2br(safe($tutorial['description'])) ?></p>

          <div class="video-placeholder video-placeholder-flush">
            <?php if ($embedUrl): ?>
              <iframe width="100%" height="100%" src="<?= safe($embedUrl) ?>" title="Guide Video" frameborder="0"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                      allowfullscreen style="aspect-ratio:16/9;"></iframe>
            <?php elseif (!empty($tutorial['guide_video_file'])): ?>
              <video controls style="width:100%;aspect-ratio:16/9;">
                <source src="<?= safe($tutorial['guide_video_file']) ?>" />
              </video>
            <?php elseif (!empty($tutorial['guide_video_url'])): ?>
              <div class="play">▶</div>
              <p><a href="<?= safe($tutorial['guide_video_url']) ?>" target="_blank" rel="noopener" class="text-primary link-underline">Watch Source Video</a></p>
            <?php else: ?>
              <div class="play">▶</div>
              <p>No guide video provided.</p>
            <?php endif; ?>
          </div>

          <?php if (count($resources) > 0): ?>
            <div class="mobile-resources-block">
              <h4 class="sidebar-resources-title">
                Downloads &amp; Assets
                <span class="sidebar-resources-count"><?= count($resources) ?></span>
              </h4>
              <?php foreach ($resources as $r):
                $is_preset = ($r['resource_type'] === 'preset');
                $icon      = $is_preset ? '🎁' : '📦';
              ?>
                <a href="<?= safe($r['resource_url']) ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="sidebar-resource-item">
                  <span class="sidebar-resource-icon" aria-hidden="true"><?= $icon ?></span>
                  <span class="sidebar-resource-name"><?= safe($r['resource_name']) ?></span>
                  <span class="sidebar-resource-dl" aria-hidden="true">↓</span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="rating-block">
            <div class="rating-block-header">
              <h3>Rate This Tutorial</h3>
              <span class="text-muted rating-total-text" id="rating-total">
                <?= $rating_stats['total'] ?> <?= $rating_stats['total'] === 1 ? 'rating' : 'ratings' ?>
              </span>
            </div>

            <div class="rating-stats">
              <div class="rating-stat">
                <span class="value" id="stat-quality"><?= $rating_stats['total'] > 0 ? number_format($rating_stats['avg_quality'], 1) : '—' ?></span>
                <span class="label">Quality</span>
              </div>
              <div class="rating-stat">
                <span class="value" id="stat-clarity"><?= $rating_stats['total'] > 0 ? number_format($rating_stats['avg_clarity'], 1) : '—' ?></span>
                <span class="label">Easy to Read</span>
              </div>
            </div>

            <?php if ($is_author): ?>
              <p class="rating-author-note">
                You can't rate your own tutorial, but you can see how others rated it above.
              </p>
            <?php else: ?>
              <div class="rating-row">
                <span class="rating-label">Quality:</span>
                <div class="stars interactive" data-type="quality" data-value="<?= (int)($your_rating['quality_rating'] ?? 0) ?>">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= $i <= (int)($your_rating['quality_rating'] ?? 0) ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span>
                  <?php endfor; ?>
                </div>
              </div>
              <div class="rating-row">
                <span class="rating-label">Easy to Read:</span>
                <div class="stars interactive" data-type="clarity" data-value="<?= (int)($your_rating['clarity_rating'] ?? 0) ?>">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= $i <= (int)($your_rating['clarity_rating'] ?? 0) ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span>
                  <?php endfor; ?>
                </div>
              </div>

              <div class="rating-actions">
                <button type="button" id="submit-rating" class="btn-primary rating-submit-btn" disabled
                        data-tutorial-id="<?= (int)$id ?>"
                        data-csrf="<?= safe(csrf_token()) ?>">
                  <?= $your_rating ? 'Update Rating' : 'Submit Rating' ?>
                </button>
                <span id="rating-feedback" class="rating-feedback"></span>
              </div>
            <?php endif; ?>
          </div>

          <h2 class="section-title">Step-by-Step</h2>
          <div class="steps-grid">
            <?php if (count($steps) > 0): ?>
              <?php foreach ($steps as $step): ?>
                <article class="step-card">
                  <h3>Step <?= (int)$step['step_number'] ?></h3>
                  <p class="step-title-bold"><?= safe($step['step_title']) ?></p>
                  <p><?= nl2br(safe($step['step_description'])) ?></p>
                </article>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted">No steps have been added for this tutorial yet.</p>
            <?php endif; ?>
          </div>
        </section>

        <aside class="right-panel">
          <?php
            $popular_exclude_id = $id;
            include 'includes/popular-tutorials.php';
          ?>

          <?php if ($has_result): ?>
            <div class="panel">
              <h3>Final Result</h3>
              <?php if ($has_result_file): ?>
                <video controls style="width:100%;border-radius:8px;aspect-ratio:16/9;">
                  <source src="<?= safe($tutorial['result_video_file']) ?>" />
                </video>
              <?php elseif ($result_embed): ?>
                <iframe width="100%" src="<?= safe($result_embed) ?>" frameborder="0" allowfullscreen style="aspect-ratio:16/9;border-radius:8px;"></iframe>
              <?php else: ?>
                <a href="<?= safe($tutorial['result_video_url']) ?>" target="_blank" rel="noopener" class="text-primary">Watch Result Video</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </aside>
      </div>
    </main>

    <!-- ============================================================
         Share Tutorial Modal — copy the tutorial link
         ============================================================ -->
    <div class="share-modal" id="share-modal" role="dialog" aria-modal="true" aria-labelledby="share-modal-title">
      <div class="share-modal-inner">
        <div class="share-modal-header">
          <h2 id="share-modal-title">Share Tutorial</h2>
          <button type="button" class="share-modal-close" id="share-modal-close" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <div class="share-modal-body">
          <p class="share-modal-desc">
            Copy this link and send it to anyone you want to share this tutorial with.
          </p>

          <div class="share-link-box">
            <input type="text"
                   id="share-url-input"
                   readonly
                   value="<?= safe($share_url) ?>"
                   aria-label="Shareable tutorial link" />
            <button type="button" class="btn-primary share-copy-btn" id="share-copy-btn">Copy</button>
          </div>
        </div>

        <div class="share-modal-footer">
          <button type="button" class="btn-outline" id="share-modal-cancel">Close</button>
        </div>
      </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script src="assets/js/tutorial-detail.js"></script>
    <script src="assets/js/tutorial-share.js"></script>
  </div><!-- /.app -->
</body>
</html>