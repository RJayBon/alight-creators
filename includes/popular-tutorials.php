<?php
/**
 * Popular Tutorials panel.
 * Optional:
 *   $popular_tutorials  — pre-fetched array (skips DB query if provided)
 *   $popular_exclude_id — tutorial_id to exclude (e.g. the currently-viewed one)
 */
if (!isset($popular_tutorials) || !is_array($popular_tutorials)) {
    $popular_exclude_id = $popular_exclude_id ?? null;
    $popular_tutorials  = get_popular_tutorials($pdo, 6, $popular_exclude_id);
}
?>
<div class="panel">
  <h4>Popular Tutorials</h4>

  <?php if (count($popular_tutorials) > 0): ?>
    <ul class="popular-tut-list">
      <?php foreach ($popular_tutorials as $pop): ?>
        <li>
          <a href="tutorial-detail.php?id=<?= (int)$pop['tutorial_id'] ?>" class="popular-tut-item">
            <span class="popular-tut-title"><?= safe($pop['title']) ?></span>
            <span class="popular-tut-meta">
              <span class="badge <?= badge_for($pop['category']) ?>"><?= safe($pop['category']) ?></span>
              <span class="popular-tut-views"><?= (int)$pop['views'] ?> views</span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="text-muted" style="font-size:.85rem;">No tutorials yet.</p>
  <?php endif; ?>
</div>