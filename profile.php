<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();

$acting_id = (int)$_SESSION['user_id'];
$is_admin  = is_admin();

$target_id = $acting_id;
if ($is_admin && isset($_GET['id'])) {
    $maybe = (int)$_GET['id'];
    if ($maybe > 0) $target_id = $maybe;
}

$is_self    = ($target_id === $acting_id);
$admin_mode = ($is_admin && !$is_self);

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$target_id]);
$target = $stmt->fetch();

if (!$target) {
    header("Location: " . ($admin_mode ? "admin-users.php" : "404.php"));
    exit;
}

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please refresh and try again.";
        $messageType = "error";
    }

    elseif (($_POST['action'] ?? '') === 'delete_account') {
        try {
            $files_to_delete = [];
            if (!empty($target['avatar_path'])) $files_to_delete[] = $target['avatar_path'];
            if (!empty($target['banner_path'])) $files_to_delete[] = $target['banner_path'];

            $tst = $pdo->prepare("
                SELECT thumbnail_path, guide_video_file, result_video_file
                FROM tutorials WHERE user_id = ?
            ");
            $tst->execute([$target_id]);
            foreach ($tst->fetchAll() as $t) {
                foreach (['thumbnail_path','guide_video_file','result_video_file'] as $col) {
                    if (!empty($t[$col])) $files_to_delete[] = $t[$col];
                }
            }

            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$target_id]);
            foreach ($files_to_delete as $f) delete_file_safe($f);

            if ($admin_mode) {
                $_SESSION['flash_message'] = "User deleted successfully.";
                $_SESSION['flash_type']    = "success";
                header("Location: admin-users.php");
                exit;
            }

            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                          $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
            }
            session_destroy();
            header("Location: login.php");
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not delete user. Try again.";
            $messageType = "error";
        }
    }

    else {
        $name           = trim($_POST['name'] ?? '');
        $handle         = normalize_handle($_POST['handle'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $bio            = trim($_POST['bio'] ?? '');
        $gender         = $_POST['gender'] ?? '';
        $gender_custom  = trim($_POST['gender_custom'] ?? '');
        $birthdate_raw  = trim($_POST['birthdate'] ?? '');

        $new_password   = $admin_mode ? trim($_POST['new_password'] ?? '') : '';
        $role           = $admin_mode
                            ? ($_POST['role'] ?? ($target['role'] ?? 'user'))
                            : ($target['role'] ?? 'user');

        $allowed_genders = ['Male', 'Female', 'Others'];
        $allowed_roles   = ['user', 'admin'];
        $birthdate       = validate_birthdate($birthdate_raw);
        if (!in_array($role, $allowed_roles, true)) $role = 'user';

        if ($name === '' || $handle === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $message = "Please provide a valid name, handle, and email.";
            $messageType = "error";
        } elseif ($gender !== '' && !in_array($gender, $allowed_genders, true)) {
            $message = "Please select a valid gender.";
            $messageType = "error";
        } elseif ($gender === 'Others' && $gender_custom === '') {
            $message = "Please specify the gender.";
            $messageType = "error";
        } elseif ($birthdate_raw !== '' && $birthdate === null) {
            $message = "Please enter a valid birthdate (must be 13+ and not in the future).";
            $messageType = "error";
        } elseif ($new_password !== '' && strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters.";
            $messageType = "error";
        } else {
            $new_avatar_path = null;
            $new_banner_path = null;
            try {
                $sql_parts = [
                    "full_name = ?", "username = ?", "email = ?", "bio = ?",
                    "gender = ?", "gender_custom = ?", "birthdate = ?"
                ];
                $params = [
                    $name, $handle, $email, $bio,
                    $gender ?: null,
                    $gender === 'Others' ? $gender_custom : null,
                    $birthdate,
                ];

                if ($admin_mode) {
                    $sql_parts[] = "role = ?";
                    $params[]    = $role;

                    if ($new_password !== '') {
                        $sql_parts[] = "password = ?";
                        $params[]    = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                }

                $new_avatar = upload_file(
                    $_FILES['avatar'] ?? null,
                    'uploads/avatars',
                    ['jpg','jpeg','png','gif','webp'],
                    2 * 1024 * 1024,
                    'user_' . $target_id
                );
                if ($new_avatar) {
                    $new_avatar_path = $new_avatar;
                    if (!empty($target['avatar_path'])) delete_file_safe($target['avatar_path']);
                    $sql_parts[] = "avatar_path = ?";
                    $params[]    = $new_avatar;
                }

                $new_banner = upload_file(
                    $_FILES['banner'] ?? null,
                    'uploads/banners',
                    ['jpg','jpeg','png','gif','webp'],
                    4 * 1024 * 1024,
                    'banner_' . $target_id
                );
                if ($new_banner) {
                    $new_banner_path = $new_banner;
                    if (!empty($target['banner_path'])) delete_file_safe($target['banner_path']);
                    $sql_parts[] = "banner_path = ?";
                    $params[]    = $new_banner;
                }

                if (!$new_avatar_path && !empty($_POST['remove_avatar'])) {
                    if (!empty($target['avatar_path'])) {
                        delete_file_safe($target['avatar_path']);
                    }
                    $sql_parts[] = "avatar_path = ?";
                    $params[]    = null;
                }

                if (!$new_banner_path && !empty($_POST['remove_banner'])) {
                    if (!empty($target['banner_path'])) {
                        delete_file_safe($target['banner_path']);
                    }
                    $sql_parts[] = "banner_path = ?";
                    $params[]    = null;
                }

                $params[] = $target_id;
                $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE user_id = ?";
                $pdo->prepare($sql)->execute($params);

                /* ---- Sync session for self-edits ----
                   Keeps $_SESSION['username'] and $_SESSION['role']
                   aligned with the DB row after a self-update. This
                   is defensive: an admin who edits their own profile
                   (or a role change that happens out-of-band) can't
                   leave a stale value cached in the session. */
                if ($is_self) {
                    $_SESSION['username'] = $handle;

                    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
                    $roleStmt->execute([$target_id]);
                    $freshRole = $roleStmt->fetchColumn();
                    if ($freshRole !== false) {
                        $_SESSION['role'] = $freshRole;
                    }
                }

                $_SESSION['flash_message'] = $admin_mode
                    ? "User updated successfully."
                    : "Profile updated successfully!";
                $_SESSION['flash_type']    = "success";

                header("Location: " . (
                    $admin_mode
                        ? "profile.php?id=" . $target_id
                        : "user.php?u=" . urlencode($handle)
                ));
                exit;

            } catch (PDOException $e) {
                error_log($e->getMessage());
                if ($new_avatar_path) delete_file_safe($new_avatar_path);
                if ($new_banner_path) delete_file_safe($new_banner_path);
                $message = "That username or email is already taken.";
                $messageType = "error";
            } catch (Throwable $e) {
                if ($new_avatar_path) delete_file_safe($new_avatar_path);
                if ($new_banner_path) delete_file_safe($new_banner_path);
                $message = $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

$user_name    = !empty($target['full_name']) ? $target['full_name'] : ($target['username'] ?? 'User');
$user_handle  = normalize_handle($target['username'] ?? '');
$user_email   = $target['email'] ?? '';
$user_bio     = $target['bio'] ?? '';
$user_gender  = $target['gender'] ?? '';
$user_gc      = $target['gender_custom'] ?? '';
$user_bd      = $target['birthdate'] ?? '';
$user_role    = $target['role'] ?? 'user';
$user_avatar  = resolve_avatar($target['avatar_path'] ?? null);
$user_banner  = resolve_banner($target['banner_path'] ?? null);
$has_avatar   = !empty($target['avatar_path']);
$has_banner   = !empty($target['banner_path']);

$cancel_url   = $admin_mode
                    ? 'admin-users.php'
                    : 'user.php?u=' . urlencode($user_handle);

$form_action  = 'profile.php' . ($admin_mode ? '?id=' . (int)$target_id : '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $admin_mode ? 'Edit User' : 'Edit Profile' ?> – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
  <link rel="stylesheet" href="assets/vendor/cropperjs/cropper.min.css" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="hero-glow-bg"></div>

      <div class="profile-container profile-pub-wrapper">

        <?php if ($message): ?>
          <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($message) ?>
          </div>
        <?php endif; ?>

        <div class="profile-card">

          <form id="delete_account_form"
                action="<?= $form_action ?>"
                method="POST"
                style="display:none;"
                data-delete-target="<?= $admin_mode ? safe($user_name) : '' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_account" />
          </form>

          <form action="<?= $form_action ?>"
                method="POST"
                enctype="multipart/form-data"
                id="profile-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_user" />

            <div class="banner-upload-block">
              <div class="banner-preview"
                   id="banner_preview"
                   style="<?= $user_banner ? 'background-image: url(' . safe($user_banner) . ');' : '' ?>"
                   onclick="document.getElementById('banner_upload').click();">
                <div class="banner-edit-overlay">
                  <svg viewBox="0 0 24 24">
                    <path d="M12 20h9"/>
                    <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                  </svg>
                  <span>Change Banner</span>
                </div>
              </div>
              <input type="file" id="banner_upload" name="banner" accept="image/png, image/jpeg, image/gif, image/webp" style="display:none;" />
              <p class="field-help" style="margin-top: .5rem;">Recommended: 1200 × 300 (4:1). Max 4 MB.</p>

              <?php if ($has_banner): ?>
                <label class="remove-file-label">
                  <input type="checkbox" name="remove_banner" value="1" />
                  Remove current banner
                </label>
              <?php endif; ?>
            </div>

            <div class="avatar-block">
              <div class="avatar-wrap">
                <div class="avatar-large">
                  <img src="<?= safe($user_avatar) ?>"
                       alt="Profile Picture"
                       id="avatar_preview"
                       class="avatar-preview-full" />
                </div>
                <label for="avatar_upload" class="avatar-edit-btn" title="Change Profile Picture">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                  </svg>
                </label>
                <input type="file" id="avatar_upload" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp" style="display:none;" />
              </div>
              <p class="field-help">Allowed: JPG, PNG, GIF. Max 2MB.</p>

              <?php if ($has_avatar): ?>
                <label class="remove-file-label">
                  <input type="checkbox" name="remove_avatar" value="1" />
                  Remove current avatar (reset to default)
                </label>
              <?php endif; ?>
            </div>

            <div class="grid-2 field-mb-lg">
              <div class="field">
                <label>Display Name</label>
                <input type="text" name="name" value="<?= safe($user_name) ?>" maxlength="100" required />
              </div>

              <div class="field">
                <label>Username (Unique Handle)</label>
                <div class="handle-wrapper">
                  <span class="handle-prefix">@</span>
                  <input type="text" name="handle" id="user_handle_input" class="handle-input"
                         value="<?= safe($user_handle) ?>"
                         pattern="[a-z0-9_]+"
                         title="Only lowercase letters, numbers, and underscores allowed"
                         required />
                </div>
              </div>
            </div>

            <div class="field field-mb-lg">
              <label>Email Address</label>
              <input type="email" name="email" value="<?= safe($user_email) ?>" maxlength="100" required />
            </div>

            <?php if ($admin_mode): ?>
              <div class="field field-mb-lg">
                <label>Role</label>
                <select name="role">
                  <option value="user"  <?= $user_role === 'user'  ? 'selected' : '' ?>>User</option>
                  <option value="admin" <?= $user_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
            <?php endif; ?>

            <div class="gender-field field-mb-lg">
              <label class="gender-label">Gender</label>

              <div class="gender-grid" role="radiogroup" aria-label="Gender">
                <button type="button" class="gender-option <?= $user_gender === 'Male' ? 'selected' : '' ?>" data-value="Male" role="radio" aria-checked="<?= $user_gender === 'Male' ? 'true' : 'false' ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="14" r="6"/><path d="M15 9l6-6M21 3h-5M21 3v5"/></svg>
                  <span>Male</span>
                </button>

                <button type="button" class="gender-option <?= $user_gender === 'Female' ? 'selected' : '' ?>" data-value="Female" role="radio" aria-checked="<?= $user_gender === 'Female' ? 'true' : 'false' ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="M12 15v6M9 18h6"/></svg>
                  <span>Female</span>
                </button>

                <button type="button" class="gender-option <?= $user_gender === 'Others' ? 'selected' : '' ?>" data-value="Others" role="radio" aria-checked="<?= $user_gender === 'Others' ? 'true' : 'false' ?>">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/></svg>
                  <span>Others</span>
                </button>
              </div>

              <input type="hidden" name="gender" id="gender_input" value="<?= safe($user_gender) ?>" />

              <div class="gender-custom <?= $user_gender === 'Others' ? 'show' : '' ?>" id="gender_custom_wrapper" aria-hidden="<?= $user_gender === 'Others' ? 'false' : 'true' ?>">
                <label class="gender-custom-label" for="gender_custom_input">Please specify</label>
                <input type="text" name="gender_custom" id="gender_custom_input"
                       value="<?= safe($user_gc) ?>"
                       placeholder="e.g. Non-binary" maxlength="50" autocomplete="off" />
              </div>
            </div>

            <div class="field field-mb-lg">
              <label for="profile_birthdate">Birthdate</label>
              <div class="date-input-wrapper">
                <input type="text" name="birthdate" id="profile_birthdate"
                       value="<?= safe($user_bd) ?>"
                       placeholder="YYYY-MM-DD"
                       pattern="\d{4}-\d{2}-\d{2}"
                       maxlength="10" inputmode="numeric" autocomplete="bday" />
                <input type="date" id="profile_birthdate_picker" class="date-picker-native" tabindex="-1" aria-hidden="true" />
                <button type="button" class="date-picker-btn" id="profile_birthdate_btn" aria-label="Open calendar">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                </button>
              </div>
              <span class="field-help">Optional. Must be 13 or older. Type YYYY-MM-DD or pick from the calendar.</span>
            </div>

            <div class="field <?= $admin_mode ? 'field-mb-lg' : 'field-mb-2xl' ?>">
              <label>Bio</label>
              <textarea name="bio" rows="4" class="textarea-no-resize" maxlength="250" placeholder="What kind of motion design do you do?"><?= safe($user_bio) ?></textarea>
              <span class="field-help-right">Max 250 characters</span>
            </div>

            <?php if ($admin_mode): ?>
              <div class="field field-mb-2xl">
                <label for="admin_password">Reset Password <em class="field-help-inline">(leave blank to keep current)</em></label>
                <input type="text" name="new_password" id="admin_password"
                       placeholder="New password (min 6 chars)"
                       minlength="6" autocomplete="new-password" />
                <span class="field-help">Shown as plain text so you can copy it before saving.</span>
              </div>
            <?php endif; ?>

            <div class="profile-actions-row">
              <button type="submit" class="btn-primary profile-action-btn">Save Changes</button>

              <button type="submit" form="delete_account_form" class="btn-danger-outline profile-action-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                </svg>
                <?= $admin_mode ? 'Delete User' : 'Delete Account' ?>
              </button>

              <button type="button" class="btn-outline profile-action-btn"
                      onclick="location.href='<?= safe($cancel_url) ?>'">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </main>

    <div class="crop-modal" id="crop_modal" role="dialog" aria-modal="true" aria-labelledby="crop_modal_title">
      <div class="crop-modal-inner">
        <div class="crop-modal-header">
          <h2 id="crop_modal_title">Crop Image</h2>
          <button type="button" class="crop-modal-close" id="crop_modal_close" aria-label="Close">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="crop-modal-body">
          <img id="crop_image" src="" alt="Crop preview" />
        </div>
        <div class="crop-modal-footer">
          <button type="button" class="btn-outline" id="crop_modal_cancel">Cancel</button>
          <button type="button" class="btn-primary" id="crop_modal_apply">Apply</button>
        </div>
      </div>
    </div>

    <script src="assets/vendor/cropperjs/cropper.min.js"></script>
    <script src="assets/js/gender-selector.js"></script>
    <script src="assets/js/profile.js"></script>
    <script src="assets/js/script.js"></script>
  </body>
</html>