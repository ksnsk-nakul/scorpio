# Documentation Suite — Design Spec
**Date:** 2026-06-29
**Status:** Approved

---

## Context

Scorpio is a self-hosted portfolio CMS built with Laravel 13, Inertia.js v2, and Vue 3. The project needs a full documentation suite targeting two audiences: developers (self-hosting, contributing) and non-technical end users (deploying their own portfolio). The current `README.md` is ~300 lines and mixes detail that belongs in dedicated docs files.

Repo: `https://github.com/ksnsk-nakul/scorpio`

---

## Audience

| Audience | Needs |
|---|---|
| Developers | Git clone, Docker dev, contributing, env config, role internals |
| End users / clients | ZIP install, Docker prod, OAuth walkthrough, FAQ, no-code config |

---

## File Structure

```
/
├── README.md                        ← rewrite: ~80 lines, links out to docs/
├── LICENSE                          ← MIT
├── Dockerfile                       ← production image (PHP-FPM + Nginx)
├── docker-compose.yml               ← production stack
├── docker-compose.dev.yml           ← local dev stack
└── docs/
    ├── INSTALLATION.md              ← git / Docker / zip methods
    ├── CONFIGURATION.md             ← all env vars, OAuth, GitHub, storage, mail
    └── FAQ.md                       ← setup troubleshooting + usage questions
```

---

## README.md

~80 lines. Sections:
- Badge row (PHP version, Laravel version, license, tests)
- One-line description
- Feature highlights (5–6 bullets)
- Tech stack table
- Quick Start (6-command git install)
- Links to docs/INSTALLATION.md, docs/CONFIGURATION.md, docs/FAQ.md, LICENSE

Trims duplicated detail from current README into dedicated docs files.

---

## docs/INSTALLATION.md

Three installation methods:

### Method 1 — Git (developers)
- Prerequisites: PHP 8.3+, Composer, Node 18+, SQLite
- Clone → `composer install` → `.env` setup → `php artisan migrate --seed` → `npm run dev`
- Laravel Herd shortcut for macOS users

### Method 2 — Docker
**Dev (`docker-compose.dev.yml`):**
- Single command: `docker compose -f docker-compose.dev.yml up`
- Hot-reload via volume mounts, no build step needed
- PHP 8.3, SQLite, queue worker, Vite dev server

**Prod (`docker-compose.yml`):**
- Nginx + PHP-FPM + queue worker + scheduler containers
- Env var injection via `.env` file
- Named volume for storage persistence
- `php artisan storage:link` run on startup

### Method 3 — ZIP (non-technical users)
- Download release ZIP from GitHub releases page
- Extract and upload to server via FTP/cPanel File Manager
- Run `composer install --no-dev` and `php artisan migrate --seed` via SSH or hosting terminal
- Shared hosting compatibility note: PHP 8.3+ required, `pdo_sqlite` and `fileinfo` extensions needed

---

## docs/CONFIGURATION.md

Grouped by concern:

### App Basics
`APP_NAME`, `APP_URL`, `APP_ENV`, `APP_KEY` (generate with `php artisan key:generate`)

### Database
SQLite default (`database/database.sqlite`). MySQL/Postgres: update `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### Admin Account
Set before seeding:
- `ADMIN_EMAIL` — email of the first admin user (must match OAuth login email)
- `ADMIN_NAME` — display name (default: Admin)

### Authentication
**Google OAuth:**
1. Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client ID
2. Add `{APP_URL}/auth/google/callback` to Authorized redirect URIs
3. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`

**GitHub OAuth:**
1. GitHub → Settings → Developer settings → OAuth Apps → New OAuth App
2. Set callback URL to `{APP_URL}/auth/github/callback`
3. Set `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`, `GITHUB_REDIRECT_URI`

**Email OTP:**
- Requires `MAIL_*` vars configured (SMTP)
- OTP sent to user's email; no password stored

### GitHub Integration
- Personal Access Token scopes required: `repo`, `read:org`, `project`
- Paste token in admin panel → Integrations → github → token
- `GITHUB_USERNAME` for repo listing

### File Storage
- Local (default): zero config, files stored in `storage/app/public`
- S3: set `FILESYSTEM_DISK=s3`, fill `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`

### Mail (for OTP + password reset)
`MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

---

## docs/FAQ.md

### Setup & Troubleshooting
- OAuth login fails / redirect mismatch → mismatched `REDIRECT_URI`
- 500 error after install → missing `APP_KEY`, run `php artisan key:generate`
- No admin access after first login → `ADMIN_EMAIL` doesn't match OAuth account email
- Media uploads fail → storage not linked, run `php artisan storage:link`
- GitHub sync returns 401 → token expired or missing `repo`/`project` scopes
- Docker port already in use → how to change ports in compose file
- ZIP install on shared hosting → PHP version check, missing extensions

### Usage Questions
- How do roles work / who is admin by default?
- How do I promote a user to editor or admin?
- How does GitHub sync work?
- Can I use MySQL instead of SQLite?
- How do I switch to S3 storage?
- How do I add a new page block type?

---

## Docker Files

### Dockerfile (production)
- Base: `php:8.3-fpm-alpine`
- Install: Composer, Node, npm, required PHP extensions (pdo_sqlite, pdo_mysql, fileinfo, gd, zip, pcntl)
- Build frontend assets (`npm run build`)
- Nginx config in same image or separate container
- Run `php artisan storage:link` + `php artisan migrate --force` on startup

### docker-compose.yml (production)
Services: `app` (PHP-FPM), `nginx`, `queue` (worker), `scheduler`
Volumes: `storage` (named, persistent)
Env: loaded from `.env`

### docker-compose.dev.yml (local dev)
Services: `app` (PHP + Vite), `queue`
Volumes: full project mount for hot-reload
Ports: 8000 (app), 5173 (Vite)

---

## LICENSE

MIT License. Copyright Nakul. Permissive — anyone can use, modify, distribute with attribution.

---

## Verification

1. `git clone` install → app runs, admin login works
2. `docker compose up` (dev) → hot-reload confirmed
3. `docker compose up` (prod) → Nginx serves app, queue worker processes jobs
4. ZIP install on fresh server → migrations run, media uploads work
5. `ADMIN_EMAIL` set to a real email → that user gets admin role on first login
6. FAQ answers match actual app behaviour
