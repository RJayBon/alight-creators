-- ============================================================
-- Alight Creators — Database Schema
-- Compatible with: InfinityFree (MariaDB 10.x)
-- HOW TO RUN:
--   1. Log into InfinityFree control panel → MySQL Databases
--   2. Note your assigned DB name, e.g. if0_12345678_alight
--   3. Open phpMyAdmin for that database
--   4. Click the "SQL" tab → paste this whole file → Go
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100) DEFAULT NULL,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    bio           TEXT         DEFAULT NULL,
    avatar_path   VARCHAR(255) DEFAULT NULL,
    banner_path   VARCHAR(255) DEFAULT NULL,
    gender        VARCHAR(20)  DEFAULT NULL,
    gender_custom VARCHAR(50)  DEFAULT NULL,
    birthdate     DATE         DEFAULT NULL,
    role          VARCHAR(20)  DEFAULT 'user',
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutorials (
    tutorial_id       INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT DEFAULT NULL,
    title             VARCHAR(255) NOT NULL,
    description       TEXT,
    category          VARCHAR(50)  NOT NULL,
    thumbnail_path    VARCHAR(255) DEFAULT NULL,
    guide_video_url   VARCHAR(500) DEFAULT NULL,
    guide_video_file  VARCHAR(255) DEFAULT NULL,
    result_video_url  VARCHAR(500) DEFAULT NULL,
    result_video_file VARCHAR(255) DEFAULT NULL,
    views             INT NOT NULL DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tut_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,

    INDEX idx_tutorials_views (views),
    INDEX idx_tutorials_created (created_at),
    INDEX idx_tutorials_category_created (category, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutorial_steps (
    step_id          INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id      INT NOT NULL,
    step_number      INT NOT NULL,
    step_title       VARCHAR(255) NOT NULL,
    step_description TEXT NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_step_tut FOREIGN KEY (tutorial_id)
        REFERENCES tutorials(tutorial_id) ON DELETE CASCADE,

    INDEX idx_steps_tut_order (tutorial_id, step_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutorial_resources (
    resource_id    INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id    INT NOT NULL,
    resource_type  VARCHAR(20)  NOT NULL,
    resource_name  VARCHAR(100) NOT NULL,
    resource_url   VARCHAR(500) NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resource_tut FOREIGN KEY (tutorial_id)
        REFERENCES tutorials(tutorial_id) ON DELETE CASCADE,

    INDEX idx_resource_tut (tutorial_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutorial_views (
    view_id     INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id INT NOT NULL,
    user_id     INT NOT NULL,
    viewed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_view (tutorial_id, user_id),

    CONSTRAINT fk_view_tut FOREIGN KEY (tutorial_id)
        REFERENCES tutorials(tutorial_id) ON DELETE CASCADE,
    CONSTRAINT fk_view_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutorial_ratings (
    rating_id       INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id     INT NOT NULL,
    user_id         INT NOT NULL,
    quality_rating  TINYINT UNSIGNED NOT NULL,
    clarity_rating  TINYINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_tutorial (tutorial_id, user_id),

    CONSTRAINT fk_rating_tut FOREIGN KEY (tutorial_id)
        REFERENCES tutorials(tutorial_id) ON DELETE CASCADE,
    CONSTRAINT fk_rating_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_loves (
    love_id    INT AUTO_INCREMENT PRIMARY KEY,
    lover_id   INT NOT NULL,
    loved_id   INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_love (lover_id, loved_id),

    CONSTRAINT fk_love_lover FOREIGN KEY (lover_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_love_loved FOREIGN KEY (loved_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_msg_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    reset_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    token_hash CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token (token_hash),
    INDEX idx_user  (user_id),

    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED: default administrator account
--   Username : admin
--   Email    : admin@alight.local
--   Password : AlightAdmin2026
--   ⚠ Change this password immediately after first login.
-- ============================================================
INSERT INTO users
  (full_name, username, email, password, bio, birthdate, role)
VALUES (
  'Administrator',
  'admin',
  'admin@alight.local',
  '$2a$12$oFaBgcdpbbBL7I9StPev1eKNW3VCGV/J81PSuNEDxkjNak20UpDLy',
  'Beginner motion designer on a mission to learn Alight Motion. Small steps, big edits.',
  '2007-10-01',
  'admin'
)
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  password  = VALUES(password),
  bio       = VALUES(bio),
  birthdate = VALUES(birthdate),
  role      = 'admin';