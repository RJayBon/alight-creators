<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();

$message = "";
$messageType = "";
$is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH'])
    && (int)$_SERVER['CONTENT_LENGTH'] > 0
) {
    $max = ini_get('post_max_size');
    $message = "Your upload exceeded the server limit ({$max}). Please use a smaller file or paste a video URL instead.";
    $messageType = "error";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please refresh and try again.";
        $messageType = "error";
    } else {
        $title       = trim($_POST['title'] ?? '');
        $category    = $_POST['category'] ?? 'Beginner';
        $description = trim($_POST['description'] ?? '');
        $user_id     = (int)$_SESSION['user_id'];

        $guide_video_url  = sanitize_url($_POST['guide_video_url']  ?? '');
        $result_video_url = sanitize_url($_POST['result_video_url'] ?? '');

        $validCategories = ['Beginner', 'Intermediate', 'Advanced', 'Tips & Tricks'];
        if (!in_array($category, $validCategories, true)) $category = 'Beginner';

        if ($title === '' || $description === '') {
            $message = "Title and description are required.";
            $messageType = "error";
        } else {
            $uploaded = [];
            try {
                $thumbnail_path = upload_image_as_webp(
                    $_FILES['thumbnail'] ?? null,
                    'uploads/thumbnails',
                    2 * 1024 * 1024,
                    82,
                    'tut'
                );
                if ($thumbnail_path) $uploaded[] = $thumbnail_path;

                $guide_video_file = upload_file(
                    $_FILES['guide_video_file'] ?? null,
                    'uploads/videos',
                    ['mp4','webm','ogg','mov'],
                    100 * 1024 * 1024, 'guide'
                );
                if ($guide_video_file) $uploaded[] = $guide_video_file;

                $result_video_file = upload_file(
                    $_FILES['result_video_file'] ?? null,
                    'uploads/videos',
                    ['mp4','webm','ogg','mov'],
                    100 * 1024 * 1024, 'result'
                );
                if ($result_video_file) $uploaded[] = $result_video_file;

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

                $stmt = $pdo->prepare("
                    INSERT INTO tutorials
                      (user_id, title, description, category, thumbnail_path,
                       guide_video_url, guide_video_file, result_video_url, result_video_file)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $title, $description, $category, $thumbnail_path,
                    $guide_video_url ?: null, $guide_video_file,
                    $result_video_url ?: null, $result_video_file
                ]);
                $tutorial_id = (int)$pdo->lastInsertId();

                $stepStmt = $pdo->prepare("
                    INSERT INTO tutorial_steps
                      (tutorial_id, step_number, step_title, step_description)
                    VALUES (?, ?, ?, ?)
                ");
                $n = 0;
                foreach ($cleaned as [$st, $sd]) {
                    $n++;
                    $stepStmt->execute([$tutorial_id, $n, $st, $sd]);
                }

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

                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok'       => true,
                        'redirect' => 'tutorial-detail.php?id=' . $tutorial_id,
                    ]);
                    exit;
                }

                header("Location: tutorial-detail.php?id=" . $tutorial_id);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($uploaded as $f) delete_file_safe($f);
                error_log($e->getMessage());
                $message = $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

if ($is_ajax && $messageType === 'error' && $message !== '') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add Tutorial – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="add-tut-layout">

        <aside class="sidebar add-tut-sidebar">
          <h3>Submission Tips</h3>
          <ul>
            <li>Keep titles short and searchable.</li>
            <li>URLs are generated automatically.</li>
            <li>Break it down into 3–15 clear steps.</li>
            <li>Thumbnails look best at 16:9.</li>
            <li>Presets & assets go in the Downloads section.</li>
          </ul>
        </aside>

        <section class="content page-content">

          <div class="add-tut-header">
            <h1>Add Tutorial</h1>
            <p>Share your knowledge and help fellow creators build amazing motion designs.</p>
          </div>

          <?php if ($message): ?>
            <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
              <?= safe($message) ?>
            </div>
          <?php endif; ?>

          <div class="page-scroll-area">
            <div class="form-section-card">
              <form action="add-tutorial.php" method="POST" enctype="multipart/form-data" id="tutorial-form">
                <?= csrf_field() ?>

                <div class="field field-mb-lg">
                  <label>Title</label>
                  <input type="text" id="input_title" name="title" placeholder="Smooth Camera Movement" required />
                </div>

                <div class="grid-2 field-mb-lg">
                  <div class="form-col-stack">
                    <div class="field">
                      <label>Category</label>
                      <div class="custom-select" id="category_select">
                        <select id="input_category" name="category" class="custom-select-native" tabindex="-1">
                          <option value="Beginner">Beginner</option>
                          <option value="Intermediate">Intermediate</option>
                          <option value="Advanced">Advanced</option>
                          <option value="Tips &amp; Tricks">Tips &amp; Tricks</option>
                        </select>

                        <button type="button"
                                class="custom-select-trigger"
                                aria-haspopup="listbox"
                                aria-expanded="false">
                          <span class="custom-select-label">Beginner</span>
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
                          <svg id="thumb_placeholder" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                          <img id="thumb_preview_img" src="" alt="Thumbnail Preview" class="thumb-preview-img" />
                        </div>
                        <label for="thumbnail_upload" class="btn-outline thumbnail-upload-label">Choose image</label>
                        <input type="file" id="thumbnail_upload" name="thumbnail" accept="image/png, image/jpeg, image/gif, image/webp, image/avif" style="display: none;" />
                      </div>
                    </div>
                  </div>

                  <div class="field">
                    <label>Guiding Video (Shows how to do it)</label>
                    <div class="dual-input dual-input-flex">
                      <input type="url" id="guide_url_input" name="guide_video_url" placeholder="YouTube or any URL..." class="input-compact" />
                      <div class="dual-input-divider" id="guide_divider">OR</div>
                      <input type="file" id="guide_file_input" name="guide_video_file" accept="video/*" class="file-input-sm" />
                    </div>
                  </div>
                </div>

                <div class="field field-mb-xl">
                  <label>Short Description</label>
                  <textarea name="description" rows="3" maxlength="282" class="textarea-no-resize" placeholder="Briefly describe what they will learn..." required></textarea>
                </div>

                <div class="form-section-header">
                  <h3>Steps</h3>
                  <button type="button" id="btn_add_step" class="btn-outline btn-add-step">+ Add step</button>
                </div>

                <div id="steps_container"></div>

                <div class="form-section-header resource-section-header">
                  <h3>Downloads &amp; Assets</h3>
                  <button type="button" id="btn_add_resource" class="btn-outline btn-add-step">+ Add resource</button>
                </div>
                <p class="field-help field-help-block resource-section-hint">
                  Share presets, project files, fonts, or any external downloads. Paste a Google Drive, Dropbox, MediaFire, or any direct link.
                </p>
                <div id="resources_container"></div>

                <div class="form-btn-row">
                  <button type="submit" class="btn-primary form-btn-submit">Submit Tutorial</button>
                  <button type="reset" class="btn-outline form-btn-half">Clear</button>
                  <button type="button" class="btn-outline form-btn-half" onclick="location.href='home.php'">Cancel</button>
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
              <input type="url" id="result_url_input" name="result_video_url" form="tutorial-form" placeholder="YouTube or any URL..." class="input-compact" />
              <div class="dual-input-divider" id="result_divider">OR</div>
              <input type="file" id="result_file_input" name="result_video_file" form="tutorial-form" accept="video/*" class="file-input-xs" />
            </div>
          </div>

          <div class="panel">
            <h3 class="panel-title">Preview</h3>
            <div class="preview-card-mock">
              <div class="preview-video-placeholder" id="preview_thumb_backdrop">
                Upload File or Input URL<br>YT/Any link<br><span class="side-panel-hint-tight">(Result Video)</span>
              </div>
              <div class="preview-info-mock">
                <h4 id="preview_title">Tutorial Title</h4>
                <span id="preview_badge" class="badge badge-beginner">Beginner</span>
              </div>
            </div>
          </div>
        </aside>

      </div>
    </main>

    <script src="assets/js/script.js"></script>
    <script src="assets/js/tutorial-form.js"></script>
    <script src="assets/js/upload-progress.js"></script>
  </div><!-- /.app -->
</body>
</html>