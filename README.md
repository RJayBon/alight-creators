# 🎬 Alight Creators

> A community-powered learning platform for mastering **Alight Motion** — built with vanilla PHP, MySQL, and zero frameworks.

![Status](https://img.shields.io/badge/status-active-brightgreen)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-blue)

---

## 📖 About

**Alight Creators** is a beginner-friendly tutorial platform where motion designers share step-by-step Alight Motion guides, rate each other's work, and discover new techniques — all wrapped in a sleek dark UI with neon cyan and purple accents.

Whether you're picking up the app for the first time or hunting for advanced keyframe tricks, Alight Creators gives you a clean, distraction-free space to learn from real creators.

---

## ✨ Features

### 📚 For Learners
- **Curated tutorial library** — filter by Beginner, Intermediate, Advanced, or Tips & Tricks
- **Step-by-step breakdowns** — clear, ordered instructions with guiding videos
- **Downloadable resources** — presets, project files, and assets attached to each tutorial
- **Personalized feed** — "Recommended" (top-rated) and "Latest" carousels on the home dashboard
- **Search & discovery** — category chips, mobile-first navigation, and a persistent popular sidebar
- **Creator profiles** — Discord-style public pages with bio, banner, avatar, and published tutorials

### ✍️ For Creators
- **Rich tutorial editor** — upload thumbnails, videos, break into up to 15 steps, attach up to 10 resources
- **Live preview panel** — see your tutorial card update in real time as you type
- **Client-side image cropping** — 1:1 avatars and 4:1 banners using Cropper.js
- **Upload progress overlay** — live percentage bar with the ability to cancel mid-upload
- **Full edit & delete control** — replace media, reorder steps, remove assets, all in one place

### 🤝 For Community
- **Dual-axis ratings** — rate tutorials on Quality and Easy-to-Read (1–5 stars each)
- **"Love" system** — show appreciation for creators with a single click
- **Copy-link sharing** — one button copies a direct URL to the tutorial, ready to paste anywhere
- **Contact form** — send messages directly to the platform owners

### 🛡️ Security
- Bcrypt password hashing (`password_hash` with `PASSWORD_DEFAULT`)
- CSRF protection on every state-changing request
- Session-based rate limiting (login, register, contact, rating, sharing, love)
- Prepared statements — zero SQL injection surface
- HTML escaping via `safe()` helper — zero XSS surface
- MIME-verified file uploads (extension + `finfo` sniffing)
- SHA-256 hashed password reset tokens with 15-minute TTL
- Hotlink protection on uploaded media via `.htaccess`

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 7.4+ (procedural, no framework) |
| **Database** | MySQL 5.7+ / MariaDB 10.2+ (PDO prepared statements) |
| **Frontend** | HTML5 · CSS3 (custom properties) · Vanilla ES6 JavaScript |
| **Auth** | PHP sessions · bcrypt · CSRF tokens |
| **Media** | Local file storage (`/uploads/`) |
| **Fonts** | Varela Round (Google Fonts CDN) |
| **Icons** | Inline SVG only |
| **Third-party** | [Cropper.js 1.6.2](https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/) — local copy |

**Design philosophy:** No build step. No bundler. No npm. Just clean, readable code that runs anywhere PHP does.

---

## 📋 Requirements

Before you begin, make sure you have:

- **PHP 7.4 or higher** (tested on 8.0, 8.1, 8.2)
- **MySQL 5.7+** or **MariaDB 10.2+**
- **Apache** with `mod_rewrite`, `mod_headers`, `mod_authz_core` enabled
  - (Nginx works but requires manual conversion of `.htaccess` rules)
- **A local server stack**: XAMPP, WAMP, MAMP, Laragon, or PHP's built-in server
- **Write permissions** on `uploads/` and its subfolders

---

## 🚀 Local Setup (XAMPP / WAMP / MAMP)

### 1. Clone or download the project

Place the project folder inside your web server's document root:

- **XAMPP (Windows)** → `C:\xampp\htdocs\alight-creators\`
- **WAMP** → `C:\wamp64\www\alight-creators\`
- **MAMP (macOS)** → `/Applications/MAMP/htdocs/alight-creators/`

### 2. Import the database

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and:

1. Click **SQL** tab
2. Paste the entire contents of `Database Code.sql`
3. Click **Go**

Or from the command line:

```bash
mysql -u root -p < "Database Code.sql"
