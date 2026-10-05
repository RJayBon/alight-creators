<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();

$user_id     = (int)$_SESSION['user_id'];
$tutorial_id = (int)($_GET['id'] ?? 0);

if ($tutorial_id <= 0) {
    header("Location: home.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tutorials WHERE tutorial_id = ?");
$stmt->execute([$tutorial_id]);
$tutorial = $stmt->fetch();

if (!$tutorial) {
    header("Location: 404.php");
    exit;
}

if (!can_manage_tutorial($tutorial)) {
    $_SESSION['flash_message'] = "You can only edit your own tutorials.";
    $_SESSION['flash_type']    = "error";
    header("Location: tutorial-detail.php?id=" . $tutorial_id);
    exit;
}

$is_own     = ((int)$tutorial['user_id'] === $user_id);
$admin_edit = (!$is_own && is_admin());

$owner = null;
if ($admin_edit) {
    $o = $pdo->prepare("SELECT full_name, username FROM users WHERE user_id = ?");
    $o->execute([$tutorial['user_id']]);
    $owner = $o->fetch() ?: null;
}

$stepStmt = $pdo->prepare("SELECT * FROM tutorial_steps WHERE tutorial_id = ? ORDER BY step_number ASC");
$stepStmt->execute([$tutorial_id]);
$steps = $stepStmt->fetchAll();

/* ---- Load existing resources ---- */
$resStmt = $pdo->prepare("
    SELECT resource_type, resource_name, resource_url
    FROM tutorial_resources
    WHERE tutorial_id = ?
    ORDER BY resource_id ASC
");
$resStmt->execute([$tutorial_id]);
$existing_resources = $resStmt->fetchAll();

$message = "";
$messageType = "";
$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please refresh and try again.";
        $messageType = "error";
    } else {
        $title       = trim($_POST['title'] ?? '');
        $category    = $_POST['category'] ?? 'Beginner';
        $description = trim($_POST['description'] ?? '');

        $guide_video_url  = sanitize_url($_POST['guide_video_url']  ?? '');
        $result_video_url = sanitize_url($_POST['result_video_url'] ?? '');

        $validCategories = ['Beginner', 'Intermediate', 'Advanced', 'Tips & Tricks'];
        if (!in_array($category, $validCategories, true)) $category = 'Beginner';

        if ($title === '' || $description === '') {
            $message = "Title and description are required.";
            $messageType = "error";
        } else {
            $files_to_delete = [];
            $new_files       = [];
            $newValues       = [
                'title'            => $title,
                'description'      => $description,
                'category'         => $category,
                'guide_video_url'  => $guide_video_url  ?: null,
                'result_video_url' => $result_video_url ?: null,
            ];

            try {
                if (!empty($_POST['remove_thumbnail'])) {
                    if (!empty($tutorial['thumbnail_path'])) $files_to_delete[] = $tutorial['thumbnail_path'];
                    $newValues['thumbnail_path'] = null;
                } else {
                    $up = upload_file($_FILES['thumbnail'] ?? null, 'uploads/thumbnails',
                                      ['jpg','jpeg','png','gif','webp'], 2 * 1024 * 1024, 'tut');
                    if ($up) {
                        if (!empty($tutorial['thumbnail_path'])) $files_to_delete[] = $tutorial['thumbnail_path'];
                        $newValues['thumbnail_path'] = $up;
                        $new_files[] = $up;
                    }
                }

                if (!empty($_POST['remove_guide_video_file'])) {
                    if (!empty($tutorial['guide_video_file'])) $files_to_delete[] = $tutorial['guide_video_file'];
                    $newValues['guide_video_file'] = null;
                } else {
                    $up = upload_file($_FILES['guide_video_file'] ?? null, 'uploads/videos',
                                      ['mp4','webm','ogg','mov'], 100 * 1024 * 1024, 'guide');
                    if ($up) {
                        if (!empty($tutorial['guide_video_file'])) $files_to_delete[] = $tutorial['guide_video_file'];
                        $newValues['guide_video_file'] = $up;
                        $new_files[] = $up;
                    }
                }

                if (!empty($_POST['remove_result_video_file'])) {
                    if (!empty($tutorial['result_video_file'])) $files_to_delete[] = $tutorial['result_video_file'];
                    $newValues['result_video_file'] = null;
                } else {
                    $up = upload_file($_FILES['result_video_file'] ?? null, 'uploads/videos',
                                      ['mp4','webm','ogg','mov'], 100 * 1024 * 1024, 'result');
                    if ($up) {
                        if (!empty($tutorial['result_video_file'])) $files_to_delete[] = $tutorial['result_video_file'];
                        $newValues['result_video_file'] = $up;
                        $new_files[] = $up;
                    }
                }

                /* ---- Steps ---- */
                $stepTitles = $_POST['step_title'] ?? [];
                $stepDescs  = $_POST['step_desc'] ?? [];
                $cleaned = [];
                foreach ($stepTitles as $i => $st) {
                    $st = trim($st);
                    $sd = trim($stepDescs[$i] ?? '');
                    if ($st !== '' && $sd !== '') $cleaned[] = [$st, $sd];
                }
                if (empty($cleaned)) {
                    throw new RuntimeException("Please add at least one complete step.");
                }

                /* ---- Resources ---- */
                $resourceTypes = $_POST['resource_type'] ?? [];
                $resourceNames = $_POST['resource_name'] ?? [];
                $resourceUrls  = $_POST['resource_url']  ?? [];
                $resources = [];
                foreach ($resourceTypes as $i => $type) {
                    $type  = in_array($type, ['preset', 'asset'], true) ? $type : 'preset';
                    $rname = trim($resourceNames[$i] ?? '');
                    $rurl  = sanitize_url($resourceUrls[$i] ?? '');
                    if ($rname !== '' && $rurl) {
                        $resources[] = ['type' => $type, 'name' => $rname, 'url' => $rurl];
                    }
                }

                $pdo->beginTransaction();

                $setParts = [];
                $params   = [];
                foreach ($newValues as $col => $val) {
                    $setParts[] = "$col = ?";
                    $params[]   = $val;
                }
                $params[] = $tutorial_id;
                $params[] = (int)$tutorial['user_id'];

                $sql = "UPDATE tutorials SET " . implode(', ', $setParts) . " WHERE tutorial_id = ? AND user_id = ?";
                $pdo->prepare($sql)->execute($params);

                $pdo->prepare("DELETE FROM tutorial_steps WHERE tutorial_id = ?")->execute([$tutorial_id]);

                $stepStmt = $pdo->prepare("
                    INSERT INTO tutorial_steps (tutorial_id, step_number, step_title, step_description)
                    VALUES (?, ?, ?, ?)
                ");
                $n = 0;
                foreach ($cleaned as [$st, $sd]) {
                    $n++;
                    $stepStmt->execute([$tutorial_id, $n, $st, $sd]);
                }

                /* ---- Replace resources ---- */
                $pdo->prepare("DELETE FROM tutorial_resources WHERE tutorial_id = ?")
                    ->execute([$tutorial_id]);

                if (!empty($resources)) {
                    $rStmt = $pdo->prepare("
                        INSERT INTO tutorial_resources
                          (tutorial_id, resource_type, resource_name, resource_url)
                        VALUES (?, ?, ?, ?)
                    ");
                    foreach ($resources as $r) {
                        $rStmt->execute([$tutorial_id, $r['type'], $r['name'], $r['url']]);
                    }
                }

                $pdo->commit();

                foreach ($files_to_delete as $f) delete_file_safe($f);

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok'       => true,
                        'redirect' => 'tutorial-detail.php?id=' . $tutorial_id,
                    ]);
                    exit;
                }

                $_SESSION['flash_message'] = $admin_edit
                    ? "Tutorial updated successfully (admin action)."
                    : "Tutorial updated successfully!";
                $_SESSION['flash_type']    = "success";
                header("Location: tutorial-detail.php?id=" . $tutorial_id);
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($new_files as $f) delete_file_safe($f);
                error_log($e->getMessage());
                $message = $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

/* AJAX callers get JSON errors instead of HTML */
if ($is_ajax && $messageType === 'error' && $message !== '') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_title        = $_POST['title']             ?? $tutorial['title'];
    $form_category     = $_POST['category']          ?? $tutorial['category'];
    $form_description  = $_POST['description']       ?? $tutorial['description'];
    $form_guide_url    = $_POST['guide_video_url']   ?? $tutorial['guide_video_url'];
    $form_result_url   = $_POST['result_video_url']  ?? $tutorial['result_video_url'];
} else {
    $form_title        = $tutorial['title'];
    $form_category     = $tutorial['category'];
    $form_description  = $tutorial['description'];
    $form_guide_url    = $tutorial['guide_video_url'];
    $form_result_url   = $tutorial['result_video_url'];
}

$steps_json = json_encode(array_map(fn($s) => [
    'title' => $s['step_title'],
    'desc'  => $s['step_description'],
], $steps), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/* ---- Resources → JSON for the form builder ---- */
$resources_json = json_encode(array_map(fn($r) => [
    'type' => $r['resource_type'],
    'name' => $r['resource_name'],
    'url'  => $r['resource_url'],
], $existing_resources), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Tutorial – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="add-tut-layout">

        <aside class="sidebar add-tut-sidebar">
          <h3>Editing Tips</h3>
          <ul>
            <li>Replace media by uploading a new file.</li>
            <li>Check "Remove" to delete a file without replacing it.</li>
            <li>All steps below will be saved in order.</li>
            <li>Videos should stay under 100 MB.</li>
            <li>Resources are replaced in place — edit or delete rows freely.</li>
          </ul>
        </aside>

        <section class="content page-content">

          <div class="add-tut-header">
            <h1>Edit Tutorial</h1>
            <p>
              <?php if ($admin_edit): ?>
                Admin mode — editing
                <strong><?= safe($owner['full_name'] ?? $owner['username'] ?? 'another user') ?></strong>'s
                tutorial.
              <?php else: ?>
                Update your tutorial details, steps, or media.
              <?php endif; ?>
            </p>
          </div>

          <?php if ($admin_edit): ?>
            <div class="admin-edit-banner">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
              <span>You are editing another user's tutorial as an administrator.</span>
            </div>
          <?php endif; ?>

          <?php if ($message): ?>
            <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
              <?= safe($message) ?>
            </div>
          <?php endif; ?>

          <div class="page-scroll-area">
            <div class="form-section-card">
              <form action="edit-tutorial.php?id=<?= (int)$tutorial_id ?>" method="POST" enctype="multipart/form-data" id="tutorial-form">
                <?= csrf_field() ?>

                <div class="field field-mb-lg">
                  <label>Title</label>
                  <input type="text" id="input_title" name="title" value="<?= safe($form_title) ?>" required />
                </div>

                <div class="grid-2 field-mb-lg">
                  <div class="form-col-stack">
                    <div class="field">
                      <label>Category</label>
                      <div class="custom-select" id="category_select">
                        <select id="input_category" name="category" class="custom-select-native" tabindex="-1">
                          <?php foreach (['Beginner','Intermediate','Advanced','Tips & Tricks'] as $cat): ?>
                            <option value="<?= safe($cat) ?>" <?= $form_category === $cat ? 'selected' : '' ?>>
                              <?= safe($cat) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>

                        <button type="button"
                                class="custom-select-trigger"
                                aria-haspopup="listbox"
                                aria-expanded="false">
                          <span class="custom-select-label"><?= safe($form_category) ?></span>
                          <svg class="custom-select-chevron" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"/>
                          </svg>
                        </button>

                        <ul class="custom-select-menu" role="listbox" tabindex="-1">
                          <li role="option" data-value="Beginner">Beginner</li>
                          <li role="option" data-value="Intermediate">Intermediate</li>
                          <li role="option" data-value="Advanced">Advanced</li>
                          <li role="option" data-value="Tips &amp; Tricks">Tips &amp; Tricks</li>
                        </ul>
                      </div>
                    </div>

                    <div class="field">
                      <label>Thumbnail</label>
                      <div class="thumbnail-upload-box">
                        <div class="thumbnail-preview-thumb thumbnail-preview-rel">
                          <?php if (!empty($tutorial['thumbnail_path'])): ?>
                            <img id="thumb_preview_img" src="<?= safe($tutorial['thumbnail_path']) ?>" alt="Current thumbnail" class="thumb-preview-img thumb-preview-img-visible" />
                          <?php else: ?>
                            <svg id="thumb_placeholder" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <img id="thumb_preview_img" src="" alt="Thumbnail Preview" class="thumb-preview-img" />
                          <?php endif; ?>
                        </div>
                        <label for="thumbnail_upload" class="btn-outline thumbnail-upload-label">
                          <?= !empty($tutorial['thumbnail_path']) ? 'Replace image' : 'Choose image' ?>
                        </label>
                        <input type="file" id="thumbnail_upload" name="thumbnail" accept="image/png, image/jpeg, image/gif, image/webp" style="display: none;" />
                      </div>

                      <?php if (!empty($tutorial['thumbnail_path'])): ?>
                        <label class="remove-file-label">
                          <input type="checkbox" name="remove_thumbnail" value="1" />
                          Remove current thumbnail
                        </label>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="field">
                    <label>Guiding Video (Shows how to do it)</label>
                    <div class="dual-input dual-input-flex">
                      <input type="url" id="guide_url_input" name="guide_video_url"
                             value="<?= safe($form_guide_url) ?>"
                             placeholder="YouTube or any URL..." class="input-compact" />
                      <div class="dual-input-divider" id="guide_divider">OR</div>
                      <input type="file" id="guide_file_input" name="guide_video_file" accept="video/*" class="file-input-sm" />
                    </div>

                    <?php if (!empty($tutorial['guide_video_file'])): ?>
                      <label class="remove-file-label">
                        <input type="checkbox" name="remove_guide_video_file" value="1" />
                        Remove current guide video file
                        <span class="remove-file-name">(<?= safe(basename($tutorial['guide_video_file'])) ?>)</span>
                      </label>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="field field-mb-xl">
                  <label>Short Description</label>
                  <textarea name="description" rows="3" maxlength="282" class="textarea-no-resize" required><?= safe($form_description) ?></textarea>
                </div>

                <div class="form-section-header">
                  <h3>Steps</h3>
                  <button type="button" id="btn_add_step" class="btn-outline btn-add-step">+ Add step</button>
                </div>

                <div id="steps_container"></div>

                <!-- ============ Downloads & Assets ============ -->
                <div class="form-section-header resource-section-header">
                  <h3>Downloads &amp; Assets</h3>
                  <button type="button" id="btn_add_resource" class="btn-outline btn-add-step">+ Add resource</button>
                </div>
                <p class="field-help field-help-block resource-section-hint">
                  Share presets, project files, fonts, or any external downloads. Paste a Google Drive, Dropbox, MediaFire, or any direct link.
                </p>
                <div id="resources_container"></div>

                <div class="form-btn-row">
                  <button type="submit" class="btn-primary form-btn-submit">Save Changes</button>
                  <button type="button" class="btn-outline form-btn-half" onclick="location.href='tutorial-detail.php?id=<?= (int)$tutorial_id ?>'">Cancel</button>
                </div>
              </form>
            </div>
          </div>
        </section>

        <aside class="right-panel page-content add-tut-right">
          <div class="panel">
            <h3 class="panel-title">Result Video</h3>
            <p class="panel-hint">Shows the final outcome</p>
            <div class="dual-input dual-input-compact">
              <input type="url" id="result_url_input" name="result_video_url"
                     value="<?= safe($form_result_url) ?>"
                     form="tutorial-form" placeholder="YouTube or any URL..." class="input-compact" />
              <div class="dual-input-divider" id="result_divider">OR</div>
              <input type="file" id="result_file_input" name="result_video_file" form="tutorial-form" accept="video/*" class="file-input-xs" />
            </div>

            <?php if (!empty($tutorial['result_video_file'])): ?>
              <label class="remove-file-label" style="margin-top:.5rem;">
                <input type="checkbox" name="remove_result_video_file" value="1" form="tutorial-form" />
                Remove current result video
              </label>
            <?php endif; ?>
          </div>

          <div class="panel">
            <h3 class="panel-title">Preview</h3>
            <div class="preview-card-mock">
              <div class="preview-video-placeholder" id="preview_thumb_backdrop"
                   <?= !empty($tutorial['thumbnail_path']) ? 'style="background-image:url(' . safe($tutorial['thumbnail_path']) . ');"' : '' ?>>
                <?php if (empty($tutorial['thumbnail_path'])): ?>
                  Upload File or Input URL<br>YT/Any link<br><span class="side-panel-hint-tight">(Result Video)</span>
                <?php endif; ?>
              </div>
              <div class="preview-info-mock">
                <h4 id="preview_title"><?= safe($form_title) ?></h4>
                <span id="preview_badge" class="badge <?= badge_for($form_category) ?>"><?= safe($form_category) ?></span>
              </div>
            </div>
          </div>
        </aside>

      </div>
    </main>

    <script type="application/json" id="existing-steps-data"><?= $steps_json ?></script>
    <script type="application/json" id="existing-resources-data"><?= $resources_json ?></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/tutorial-form.js"></script>
    <script src="assets/js/upload-progress.js"></script>
  </div><!-- /.app -->
</body>
</html>