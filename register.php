<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require 'config/rate_limit.php';
require_guest();

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_limit_allow('register', 3, 3600)) {
        $message = "Too many sign-up attempts from this session. Try again later.";
        $messageType = "error";
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please try again.";
        $messageType = "error";
    } else {
        $name             = trim($_POST['name'] ?? '');
        $handle           = normalize_handle($_POST['username'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $gender           = $_POST['gender'] ?? '';
        $gender_custom    = trim($_POST['gender_custom'] ?? '');
        $birthdate_raw    = trim($_POST['birthdate'] ?? '');

        $allowed_genders = ['Male', 'Female', 'Others'];
        $birthdate = validate_birthdate($birthdate_raw);

        if ($name === '' || $handle === '' || $email === '' || $password === '' || $confirm_password === '') {
            $message = "Please fill in all required fields.";
            $messageType = "error";
        } elseif (strlen($name) > 100) {
            $message = "Display name is too long (max 100).";
            $messageType = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
        } elseif (strlen($password) < 6) {
            $message = "Password must be at least 6 characters.";
            $messageType = "error";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
            $messageType = "error";
        } elseif (!in_array($gender, $allowed_genders, true)) {
            $message = "Please select a gender.";
            $messageType = "error";
        } elseif ($gender === 'Others' && $gender_custom === '') {
            $message = "Please specify your gender.";
            $messageType = "error";
        } elseif ($birthdate_raw !== '' && $birthdate === null) {
            $message = "Please enter a valid birthdate (must be 13+ and not in the future).";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$handle, $email]);

                if ($stmt->rowCount() > 0) {
                    $message = "That username or email is already taken.";
                    $messageType = "error";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);

                    $default_bio = "🌱 Beginner motion designer on a mission to learn Alight Motion. Small steps, big edits.";

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                          (full_name, username, email, password, bio, gender, gender_custom, birthdate)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name, $handle, $email, $hashed, $default_bio,
                        $gender,
                        $gender === 'Others' ? $gender_custom : null,
                        $birthdate
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['user_id']  = (int)$pdo->lastInsertId();
                    $_SESSION['username'] = $handle;
                    $_SESSION['role']     = 'user';
                    $_SESSION['just_registered'] = true;

                    header("Location: home.php");
                    exit;
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Create Account – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="hero-glow-bg"></div>

      <div class="auth-container">
        <div class="auth-hero">
          <h1>Join Alight Creators</h1>
          <p>Create an account to start sharing your motion design.</p>
        </div>

        <?php if ($message): ?>
          <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($message) ?>
          </div>
        <?php endif; ?>

        <div class="auth-card">
          <form action="register.php" method="POST" id="register-form">
            <?= csrf_field() ?>

            <div class="field field-mb-lg">
              <label>Display Name</label>
              <input type="text" name="name" id="register_name_input" placeholder="E.g. RJ AY" maxlength="100" required />
            </div>

            <div class="field field-mb-lg">
              <label>Username (Unique Handle)</label>
              <div class="handle-wrapper">
                <span class="handle-prefix">@</span>
                <input type="text" name="username" id="register_username_input" class="handle-input" placeholder="username" pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores allowed" required />
              </div>
              <span class="field-help-block">This will be your public @handle.</span>
            </div>

            <div class="field field-mb-lg">
              <label>Email Address</label>
              <input type="email" name="email" placeholder="you@example.com" maxlength="100" required />
            </div>

            <div class="field field-mb-lg">
              <label for="register_birthdate">Birthdate</label>
              <div class="date-input-wrapper">
                <input type="text"
                       name="birthdate"
                       id="register_birthdate"
                       placeholder="YYYY-MM-DD"
                       pattern="\d{4}-\d{2}-\d{2}"
                       maxlength="10"
                       inputmode="numeric"
                       autocomplete="bday" />
                <input type="date"
                       id="register_birthdate_picker"
                       class="date-picker-native"
                       tabindex="-1"
                       aria-hidden="true" />
                <button type="button"
                        class="date-picker-btn"
                        id="register_birthdate_btn"
                        aria-label="Open calendar">
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

            <div class="gender-field field-mb-lg">
              <label class="gender-label">Gender</label>

              <div class="gender-grid" role="radiogroup" aria-label="Gender">
                <button type="button" class="gender-option" data-value="Male" role="radio" aria-checked="false">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="14" r="6"/><path d="M15 9l6-6M21 3h-5M21 3v5"/></svg>
                  <span>Male</span>
                </button>

                <button type="button" class="gender-option" data-value="Female" role="radio" aria-checked="false">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="M12 15v6M9 18h6"/></svg>
                  <span>Female</span>
                </button>

                <button type="button" class="gender-option" data-value="Others" role="radio" aria-checked="false">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/></svg>
                  <span>Others</span>
                </button>
              </div>

              <input type="hidden" name="gender" id="gender_input" value="" />

              <div class="gender-custom" id="gender_custom_wrapper" aria-hidden="true">
                <label class="gender-custom-label" for="gender_custom_input">Please specify</label>
                <input type="text" name="gender_custom" id="gender_custom_input" placeholder="e.g. Non-binary" maxlength="50" autocomplete="off" />
              </div>
            </div>

            <div class="field field-mb-lg">
              <label for="register_password">Password</label>
              <input type="password"
                     name="password"
                     id="register_password"
                     placeholder="Create a secure password"
                     minlength="6"
                     autocomplete="new-password"
                     required />
            </div>

            <div class="field field-mb-xl">
              <label for="register_confirm_password">Confirm Password</label>
              <input type="password"
                     name="confirm_password"
                     id="register_confirm_password"
                     placeholder="Re-enter your password"
                     minlength="6"
                     autocomplete="new-password"
                     required />
              <span class="field-help" id="confirm_password_help">Must match your password above.</span>
            </div>

            <button type="submit" class="btn-primary btn-full">Create Account</button>
          </form>

          <div class="auth-footer">
            Already have an account? <a href="login.php">Log in here</a>
          </div>
        </div>
      </div>
    </main>

    <script src="assets/js/gender-selector.js"></script>
    <script src="assets/js/register.js"></script>
    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>