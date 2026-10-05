<?php
/**
 * Category filter sidebar.
 * Optional:
 *   $sidebar_active_category — highlight a category link
 *   $sidebar_extra_html      — HTML appended below the category list
 */
$sidebar_active_category = $sidebar_active_category ?? '';
$sidebar_extra_html      = $sidebar_extra_html      ?? '';

$sidebar_categories = [
    'All'           => 'All',
    'Beginner'      => 'Beginners',
    'Intermediate'  => 'Intermediate',
    'Advanced'      => 'Advanced',
    'Tips & Tricks' => 'Tips & Tricks',
];
?>
<aside class="sidebar">
  <ul>
    <?php foreach ($sidebar_categories as $value => $label): ?>
      <li>
        <a href="tutorials.php?category=<?= urlencode($value) ?>"
           class="<?= $sidebar_active_category === $value ? 'active' : '' ?>">
          <?= safe($label) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?= $sidebar_extra_html ?>
</aside>