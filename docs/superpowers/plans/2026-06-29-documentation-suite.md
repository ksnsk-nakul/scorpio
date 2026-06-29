# Documentation Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a complete documentation suite for Scorpio — a rewritten README, MIT LICENSE, Docker files (dev + prod), and three focused docs files (INSTALLATION, CONFIGURATION, FAQ).

**Architecture:** README stays concise (~80 lines) and links out to dedicated `docs/` files. Docker support is added via a production `Dockerfile` + `docker-compose.yml` and a dev `docker-compose.dev.yml`. All docs are plain Markdown, no external tooling required.

**Tech Stack:** PHP 8.3+, Laravel 13, Inertia.js v2 + Vue 3, Tailwind CSS v4, SQLite, Docker (PHP 8.3-fpm-alpine + Nginx), MIT License

---

## File Map

| Action | File | Purpose |
|---|---|---|
| Rewrite | `README.md` | Concise overview, badges, quick-start, links |
| Create | `LICENSE` | MIT license text |
| Create | `Dockerfile` | Production PHP-FPM image |
| Create | `docker-compose.yml` | Production stack (app + nginx + queue + scheduler) |
| Create | `docker-compose.dev.yml` | Local dev stack (hot-reload, no build) |
| Create | `docs/INSTALLATION.md` | Git / Docker / ZIP install methods |
| Create | `docs/CONFIGURATION.md` | All env vars, OAuth setup, GitHub, storage, mail |
| Create | `docs/FAQ.md` | Setup troubleshooting + usage questions |

---

## Task 1: MIT LICENSE

**Files:**
- Create: `LICENSE`

- [ ] **Step 1: Create LICENSE file**

Create `LICENSE` at the project root with this exact content (replace year/name as shown):

```
MIT License

Copyright (c) 2026 Nakul Sri Kuber

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

- [ ] **Step 2: Verify file exists**

```bash
cat LICENSE
```

Expected: full MIT license text with "Nakul Sri Kuber" and year 2026.

- [ ] **Step 3: Commit**

```bash
git add LICENSE
git commit -m "docs: add MIT license"
```

---

## Task 2: Dockerfile (production)

**Files:**
- Create: `Dockerfile`
- Create: `docker/nginx.conf`

- [ ] **Step 1: Create nginx config**

Create `docker/nginx.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 2: Create Dockerfile**

Create `Dockerfile` at project root:

```dockerfile
FROM php:8.3-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    sqlite \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql fileinfo gd zip pcntl bcmath opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application
COPY . .

# Install PHP dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm ci --ignore-scripts && npm run build && rm -rf node_modules

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Storage permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Startup script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
```

- [ ] **Step 3: Create startup script**

Create `docker/start.sh`:

```bash
#!/bin/sh
set -e

cd /var/www/html

# Generate app key if not set
php artisan key:generate --no-interaction --force 2>/dev/null || true

# Run migrations
php artisan migrate --force --no-interaction

# Seed if first run (no admin user exists)
php artisan db:seed --class=RoleSeeder --force --no-interaction 2>/dev/null || true
php artisan db:seed --class=SettingSeeder --force --no-interaction 2>/dev/null || true
php artisan db:seed --class=UserSeeder --force --no-interaction 2>/dev/null || true

# Link storage
php artisan storage:link --force 2>/dev/null || true

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in foreground
nginx -g "daemon off;"
```

- [ ] **Step 4: Verify Dockerfile syntax**

```bash
docker build --no-cache -t scorpio-test . 2>&1 | tail -5
```

Expected: `Successfully built <image-id>` with no errors. If Docker is not available locally, skip this step and note it for CI verification.

- [ ] **Step 5: Commit**

```bash
git add Dockerfile docker/nginx.conf docker/start.sh
git commit -m "feat: add production Dockerfile with Nginx + PHP-FPM"
```

---

## Task 3: docker-compose.yml (production)

**Files:**
- Create: `docker-compose.yml`

- [ ] **Step 1: Create docker-compose.yml**

Create `docker-compose.yml` at project root:

```yaml
services:
  app:
    build: .
    restart: unless-stopped
    env_file: .env
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      DB_CONNECTION: sqlite
      DB_DATABASE: /var/www/html/database/database.sqlite
    volumes:
      - storage:/var/www/html/storage/app
      - sqlite:/var/www/html/database
    ports:
      - "${APP_PORT:-80}:80"

  queue:
    build: .
    restart: unless-stopped
    env_file: .env
    environment:
      APP_ENV: production
    volumes:
      - storage:/var/www/html/storage/app
      - sqlite:/var/www/html/database
    command: php artisan queue:work --sleep=3 --tries=3 --timeout=90

  scheduler:
    build: .
    restart: unless-stopped
    env_file: .env
    environment:
      APP_ENV: production
    volumes:
      - storage:/var/www/html/storage/app
      - sqlite:/var/www/html/database
    command: sh -c "while true; do php artisan schedule:run --no-interaction; sleep 60; done"

volumes:
  storage:
  sqlite:
```

- [ ] **Step 2: Verify compose file syntax**

```bash
docker compose config 2>&1 | head -10
```

Expected: valid YAML output with service names `app`, `queue`, `scheduler`. If Docker is not available, skip.

- [ ] **Step 3: Commit**

```bash
git add docker-compose.yml
git commit -m "feat: add production docker-compose with queue worker and scheduler"
```

---

## Task 4: docker-compose.dev.yml (local development)

**Files:**
- Create: `docker-compose.dev.yml`
- Create: `docker/Dockerfile.dev`

- [ ] **Step 1: Create dev Dockerfile**

Create `docker/Dockerfile.dev`:

```dockerfile
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nodejs \
    npm \
    sqlite \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    bash

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql fileinfo gd zip pcntl bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000 5173
```

- [ ] **Step 2: Create docker-compose.dev.yml**

Create `docker-compose.dev.yml` at project root:

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile.dev
    restart: unless-stopped
    env_file: .env
    environment:
      APP_ENV: local
      APP_DEBUG: "true"
      DB_CONNECTION: sqlite
      DB_DATABASE: /var/www/html/database/database.sqlite
      VITE_APP_NAME: "${APP_NAME}"
    volumes:
      - .:/var/www/html
    ports:
      - "8000:8000"
      - "5173:5173"
    command: >
      sh -c "
        composer install --no-interaction &&
        php artisan key:generate --no-interaction 2>/dev/null || true &&
        php artisan migrate --no-interaction &&
        php artisan db:seed --force --no-interaction &&
        php artisan storage:link --force 2>/dev/null || true &&
        npm install --ignore-scripts &&
        (php artisan serve --host=0.0.0.0 --port=8000 &) &&
        npm run dev -- --host
      "

  queue:
    build:
      context: .
      dockerfile: docker/Dockerfile.dev
    restart: unless-stopped
    env_file: .env
    environment:
      APP_ENV: local
    volumes:
      - .:/var/www/html
    command: php artisan queue:listen --tries=1 --timeout=0
```

- [ ] **Step 3: Verify compose file syntax**

```bash
docker compose -f docker-compose.dev.yml config 2>&1 | head -10
```

Expected: valid YAML output with service names `app`, `queue`. If Docker is not available, skip.

- [ ] **Step 4: Commit**

```bash
git add docker-compose.dev.yml docker/Dockerfile.dev
git commit -m "feat: add local dev docker-compose with hot-reload"
```

---

## Task 5: docs/INSTALLATION.md

**Files:**
- Create: `docs/INSTALLATION.md`

- [ ] **Step 1: Create INSTALLATION.md**

Create `docs/INSTALLATION.md`:

```markdown
# Installation

Scorpio can be installed three ways. Choose the method that suits your environment.

- [Method 1 — Git (recommended for developers)](#method-1--git-recommended-for-developers)
- [Method 2 — Docker](#method-2--docker)
- [Method 3 — ZIP (shared hosting)](#method-3--zip-shared-hosting)

---

## Prerequisites

All methods require:

| Requirement | Minimum version |
|---|---|
| PHP | 8.3+ |
| PHP extensions | `pdo_sqlite`, `fileinfo`, `gd`, `zip`, `pcntl` |
| Composer | 2.x |
| Node.js | 18+ |
| npm | 9+ |

> **Docker users:** all prerequisites are bundled in the Docker image — skip to Method 2.

---

## Method 1 — Git (recommended for developers)

### 1. Clone the repository

```bash
git clone https://github.com/ksnsk-nakul/scorpio.git
cd scorpio
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Set up environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set at minimum:

```env
APP_URL=http://your-domain.test
ADMIN_EMAIL=your@email.com
```

See [Configuration](CONFIGURATION.md) for the full list of required variables.

### 4. Create the database

```bash
touch database/database.sqlite
php artisan migrate --seed
```

### 5. Link storage

```bash
php artisan storage:link
```

### 6. Build assets and start the server

```bash
npm run build
php artisan serve
```

Visit `http://localhost:8000`. Log in with the Google or GitHub account matching your `ADMIN_EMAIL`.

> **Laravel Herd (macOS):** Place the project in `~/Herd/` and Herd auto-serves it at `http://scorpio.test`. Run `npm run dev` for hot-reload instead of `npm run build`.

---

## Method 2 — Docker

### Development (hot-reload)

Requires: Docker Desktop 4.x+

```bash
git clone https://github.com/ksnsk-nakul/scorpio.git
cd scorpio
cp .env.example .env
```

Edit `.env` — set `ADMIN_EMAIL` and any OAuth credentials you need.

```bash
docker compose -f docker-compose.dev.yml up
```

- App: `http://localhost:8000`
- Vite HMR: `http://localhost:5173`

The container mounts the full project directory — file changes are reflected immediately without rebuilding.

To stop:

```bash
docker compose -f docker-compose.dev.yml down
```

---

### Production

Requires: Docker Engine 24+ and Docker Compose v2+

```bash
git clone https://github.com/ksnsk-nakul/scorpio.git
cd scorpio
cp .env.example .env
```

Edit `.env` — set production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
ADMIN_EMAIL=your@email.com
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
```

```bash
docker compose up -d
```

- App: `http://localhost` (or port defined by `APP_PORT` in `.env`)
- Queue worker and scheduler start automatically as separate containers.

To view logs:

```bash
docker compose logs -f app
```

To stop:

```bash
docker compose down
```

> **Persistent data:** SQLite database and uploaded files are stored in named Docker volumes (`sqlite`, `storage`). They survive container restarts and rebuilds. To back up: `docker run --rm -v scorpio_sqlite:/data -v $(pwd):/backup alpine tar czf /backup/db-backup.tar.gz /data`

---

## Method 3 — ZIP (shared hosting)

### Requirements

- PHP 8.3+ with extensions: `pdo_sqlite`, `fileinfo`, `gd`, `zip`
- SSH or terminal access to run Composer and Artisan commands
- (MySQL/MariaDB optional — SQLite works on most hosts)

### 1. Download the ZIP

Go to [github.com/ksnsk-nakul/scorpio/releases](https://github.com/ksnsk-nakul/scorpio/releases), download the latest release `.zip`, and extract it.

### 2. Upload to your server

Upload the extracted folder contents to your server's web root (e.g. `public_html/`) via FTP or cPanel File Manager.

> Your hosting's web root should point to the `public/` subdirectory. In cPanel, set the document root to `public_html/public`.

### 3. Install PHP dependencies via SSH

```bash
cd ~/public_html   # adjust to your path
composer install --no-dev --optimize-autoloader
```

### 4. Set up environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your domain, `ADMIN_EMAIL`, and OAuth credentials.

### 5. Create the database and seed

```bash
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
```

### 6. Build frontend assets

If Node.js is available on the server:

```bash
npm install && npm run build
```

If not, build locally on your machine (`npm run build`) and upload the `public/build/` folder to the server.

### 7. Visit your site

Navigate to your domain. Log in with the OAuth account matching `ADMIN_EMAIL`.

---

## Upgrading

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
```

- [ ] **Step 2: Verify file renders correctly**

```bash
cat docs/INSTALLATION.md | head -20
```

Expected: starts with `# Installation` and shows the three method links.

- [ ] **Step 3: Commit**

```bash
git add docs/INSTALLATION.md
git commit -m "docs: add INSTALLATION guide (git, Docker, ZIP)"
```

---

## Task 6: docs/CONFIGURATION.md

**Files:**
- Create: `docs/CONFIGURATION.md`

- [ ] **Step 1: Create CONFIGURATION.md**

Create `docs/CONFIGURATION.md`:

```markdown
# Configuration

All configuration is done via the `.env` file. Copy `.env.example` to `.env` before making changes.

```bash
cp .env.example .env
```

---

## App Basics

| Variable | Required | Default | Description |
|---|---|---|---|
| `APP_NAME` | Yes | `Portfolio` | Site name shown in the browser title |
| `APP_URL` | Yes | `http://portfolio.test` | Full URL including scheme (no trailing slash) |
| `APP_ENV` | Yes | `local` | `local` for development, `production` for live |
| `APP_DEBUG` | Yes | `true` | Set to `false` in production |
| `APP_KEY` | Yes | _(empty)_ | Generate with `php artisan key:generate` |

---

## Admin Account

Set these **before** running `php artisan migrate --seed`. The seeder uses them to create the initial admin user.

| Variable | Required | Default | Description |
|---|---|---|---|
| `ADMIN_EMAIL` | Yes | `admin@portfolio.test` | Email of the admin user — must match the OAuth login email |
| `ADMIN_NAME` | No | `Admin` | Display name for the admin user |

> If you have already seeded and need to change the admin email, update the user directly in the database or via `php artisan tinker`:
> ```php
> User::where('email', 'old@email.com')->update(['email' => 'new@email.com']);
> ```

---

## Database

SQLite is the default — no additional setup needed.

| Variable | Default | Description |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | Database driver |
| `DB_DATABASE` | `database/database.sqlite` | Path to SQLite file |

**To use MySQL or PostgreSQL**, update these variables:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scorpio
DB_USERNAME=root
DB_PASSWORD=secret
```

Then create the database and run `php artisan migrate --seed`.

---

## Authentication

Scorpio supports four login methods. At least one must be configured.

### Google OAuth

1. Go to [console.cloud.google.com](https://console.cloud.google.com) → APIs & Services → Credentials
2. Click **Create Credentials** → **OAuth 2.0 Client ID** → **Web application**
3. Under **Authorized redirect URIs**, add: `{APP_URL}/auth/google/callback`
4. Copy the credentials to `.env`:

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://your-domain.test/auth/google/callback
```

5. Run `php artisan config:clear`

---

### GitHub OAuth

1. Go to [github.com/settings/developers](https://github.com/settings/developers) → **OAuth Apps** → **New OAuth App**
2. Set **Authorization callback URL** to: `{APP_URL}/auth/github/callback`
3. Copy the credentials to `.env`:

```env
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URI=http://your-domain.test/auth/github/callback
```

---

### Email + Password

No extra env vars needed — works out of the box. Password reset emails require `MAIL_*` vars configured (see [Mail](#mail) below).

---

### Email OTP

Sends a one-time code to the user's email. Requires `MAIL_*` vars configured.

---

## GitHub Integration

Used to list repositories, sync issues to tasks, and create GitHub Projects.

### Step 1 — Create a Personal Access Token

1. Go to [github.com/settings/tokens](https://github.com/settings/tokens) → **Generate new token (classic)**
2. Select scopes: `repo`, `read:org`, `project`
3. Copy the token

### Step 2 — Add to the Integrations panel

In the admin panel: **Integrations** → **Add** → set Group: `github`, Key: `token`, Value: _(your token)_

> Alternatively set `GITHUB_TOKEN` in `.env` — the GitHub controller falls back to this env var.

| Variable | Description |
|---|---|
| `GITHUB_TOKEN` | Personal access token (fallback if not set in Integrations panel) |
| `GITHUB_USERNAME` | Your GitHub username — used to list your repositories |

---

## File Storage

### Local (default)

Files are stored in `storage/app/public`. No configuration needed.

Run once after install:

```bash
php artisan storage:link
```

### S3-Compatible Storage

Set `FILESYSTEM_DISK=s3` and fill in the AWS credentials:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-key-id
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Works with AWS S3, DigitalOcean Spaces, MinIO, Cloudflare R2, and any S3-compatible provider. For path-style endpoints (e.g. MinIO), set `AWS_USE_PATH_STYLE_ENDPOINT=true`.

---

## Mail

Required for Email OTP login and password reset emails.

| Variable | Default | Description |
|---|---|---|
| `MAIL_MAILER` | `log` | `smtp`, `sendmail`, `mailgun`, `log` (dev only) |
| `MAIL_HOST` | `127.0.0.1` | SMTP server hostname |
| `MAIL_PORT` | `2525` | SMTP port (typically 587 for TLS, 465 for SSL) |
| `MAIL_USERNAME` | `null` | SMTP username |
| `MAIL_PASSWORD` | `null` | SMTP password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Sender address |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Sender display name |

Example for Gmail SMTP:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=you@gmail.com
```

> For local development, `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` — no SMTP needed.

---

## Roles & First Login

After `php artisan migrate --seed`:

- The user with `ADMIN_EMAIL` is automatically given the **admin** role
- All other first-time logins receive the **viewer** role
- Promote users via the admin panel: **Users** → change role dropdown

To manually promote a user via tinker:

```bash
php artisan tinker
>>> User::where('email', 'someone@example.com')->first()->assignRole('admin');
```
```

- [ ] **Step 2: Verify file renders correctly**

```bash
cat docs/CONFIGURATION.md | head -10
```

Expected: starts with `# Configuration`.

- [ ] **Step 3: Commit**

```bash
git add docs/CONFIGURATION.md
git commit -m "docs: add CONFIGURATION guide"
```

---

## Task 7: docs/FAQ.md

**Files:**
- Create: `docs/FAQ.md`

- [ ] **Step 1: Create FAQ.md**

Create `docs/FAQ.md`:

```markdown
# FAQ

## Setup & Troubleshooting

### I get a 500 error right after install

The app key has not been generated. Run:

```bash
php artisan key:generate
php artisan config:clear
```

If the error persists, check `storage/logs/laravel.log` for details.

---

### OAuth login fails with "redirect_uri_mismatch"

The `REDIRECT_URI` in your `.env` doesn't exactly match what's registered in the OAuth provider console.

**Fix:**
1. Check your `.env` — e.g. `GOOGLE_REDIRECT_URI=http://portfolio.test/auth/google/callback`
2. Go to Google Cloud Console (or GitHub OAuth Apps) and ensure the redirect URI listed there is character-for-character identical — same scheme (`http` vs `https`), same domain, same path.
3. Run `php artisan config:clear` after any `.env` change.

---

### I can log in but I'm not an admin

The `ADMIN_EMAIL` in your `.env` doesn't match the email address on your Google/GitHub account.

**Fix:** Update `ADMIN_EMAIL` to exactly match your OAuth account email, then re-run the seeder:

```bash
php artisan db:seed --class=UserSeeder --force
```

The seeder is idempotent — it will not create a duplicate user, only assign the admin role to the matching email.

---

### Media uploads fail silently

The storage symlink is missing. Run:

```bash
php artisan storage:link
```

Then verify: `public/storage` should be a symlink pointing to `storage/app/public`.

---

### GitHub sync returns a 401 error

Your GitHub Personal Access Token has expired or is missing required scopes.

**Fix:**
1. Go to [github.com/settings/tokens](https://github.com/settings/tokens) and generate a new token with scopes: `repo`, `read:org`, `project`
2. Update the token in the admin panel: **Integrations** → find the `github` / `token` entry → edit value
3. Run a manual sync: `php artisan github:sync`

---

### Docker: "port is already allocated" error

Another process is using port 80 (production) or 8000/5173 (dev).

**Fix:** Set a custom port in `.env`:

```env
APP_PORT=8080
```

Then restart: `docker compose down && docker compose up -d`

For dev, you can also edit `docker-compose.dev.yml` and change `"8000:8000"` to `"8080:8000"`.

---

### ZIP install on shared hosting — "Class not found" or blank page

Most commonly caused by PHP version or missing extensions.

**Check PHP version:**
```bash
php -v
```
Must be 8.3+. Contact your host if you're on an older version.

**Check required extensions:**
```bash
php -m | grep -E "pdo|fileinfo|gd|zip"
```

You should see: `pdo_sqlite` (or `pdo_mysql`), `fileinfo`, `gd`, `zip`. Enable any missing extensions via your host's PHP configuration panel or `php.ini`.

---

### Migrations fail with "unable to open database file"

The SQLite file doesn't exist yet. Create it first:

```bash
touch database/database.sqlite
php artisan migrate --seed
```

---

## Usage Questions

### How do roles work?

There are three roles:

| Role | Can do |
|---|---|
| **Admin** | Everything — settings, integrations, user management, all content |
| **Editor** | Create and edit pages, service cards, projects, tasks — cannot manage users or settings |
| **Viewer** | Read-only access to the dashboard and task list |

The user with `ADMIN_EMAIL` gets admin on first seed. All other first-time logins get viewer.

---

### How do I promote a user to editor or admin?

In the admin panel: **Users** → find the user → click the role dropdown → select the new role.

Or via tinker:

```bash
php artisan tinker
>>> User::where('email', 'someone@example.com')->first()->assignRole('editor');
```

Valid roles: `admin`, `editor`, `viewer`.

---

### How does GitHub sync work?

1. Add a Personal Access Token in **Integrations** (group: `github`, key: `token`)
2. Create a Project and link it to a GitHub repo (owner/repo format, e.g. `ksnsk-nakul/scorpio`)
3. Click **Sync Issues** on the project — open GitHub issues are imported as Tasks
4. Sync also runs automatically every hour via the Laravel scheduler

To run manually:

```bash
php artisan github:sync
```

---

### Can I use MySQL instead of SQLite?

Yes. Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=scorpio
DB_USERNAME=root
DB_PASSWORD=secret
```

Create the database, then run `php artisan migrate --seed`.

---

### How do I switch to S3 for file storage?

Set in `.env`:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

Run `php artisan config:clear`. New uploads go to S3 immediately. Existing local files are not migrated automatically.

Works with AWS S3, DigitalOcean Spaces, MinIO, and Cloudflare R2.

---

### How do I add a new page block type?

1. Add the block type identifier to `resources/js/Pages/Admin/Pages/` — the `BlockEditor` component
2. Add a corresponding form component in `resources/js/Components/Admin/`
3. Update the page renderer in the public-facing view to handle the new block type
4. The `blocks` column on the `pages` table is a JSON array — no migration needed for new block types

---

### How do I back up the database?

**SQLite (default):**

```bash
cp database/database.sqlite database/database.backup-$(date +%Y%m%d).sqlite
```

**Docker:**

```bash
docker run --rm \
  -v scorpio_sqlite:/data \
  -v $(pwd):/backup \
  alpine cp /data/database.sqlite /backup/database.backup-$(date +%Y%m%d).sqlite
```

---

### The scheduler isn't running (GitHub sync, queue jobs not processing)

The Laravel scheduler requires a cron entry on the server. Add this to your crontab (`crontab -e`):

```
* * * * * cd /path/to/scorpio && php artisan schedule:run >> /dev/null 2>&1
```

In Docker, the `scheduler` service handles this automatically.
```

- [ ] **Step 2: Verify**

```bash
cat docs/FAQ.md | head -5
```

Expected: starts with `# FAQ`.

- [ ] **Step 3: Commit**

```bash
git add docs/FAQ.md
git commit -m "docs: add FAQ (setup troubleshooting + usage)"
```

---

## Task 8: Rewrite README.md

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Replace README.md**

Replace the full contents of `README.md` with:

```markdown
# Scorpio — Portfolio CMS

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)
![Tests](https://img.shields.io/badge/tests-passing-brightgreen)

A self-hosted CMS for developers and creative professionals. Manage your portfolio site, track projects, sync GitHub issues as tasks, and control team access — all from a clean admin panel.

Built with **Laravel 13**, **Inertia.js v2**, and **Vue 3**.

---

## Features

- **Page builder** — block-based editor (Hero, Markdown, Service Cards, Gallery, CTA) with draft → publish workflow
- **Project & task tracking** — workspaces, projects, tasks with unlimited subtask nesting, threaded comments, file attachments
- **GitHub integration** — sync open issues to tasks, create GitHub Projects, list repos
- **Media library** — polymorphic image/video uploads, local disk or S3-compatible storage
- **Multi-method auth** — Google OAuth, GitHub OAuth, Email+Password, Email OTP
- **Role-based access** — Admin, Editor, Viewer with middleware-gated routes

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+, SQLite |
| Frontend | Inertia.js v2, Vue 3, Tailwind CSS v4 |
| Auth | Laravel Socialite (Google + GitHub OAuth) |
| RBAC | Spatie Laravel-Permission v8 |
| Testing | Pest — feature & unit tests |
| Storage | Local disk (S3-swappable) |

---

## Quick Start

```bash
git clone https://github.com/ksnsk-nakul/scorpio.git && cd scorpio
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan storage:link
npm run build && php artisan serve
```

Set `ADMIN_EMAIL` in `.env` to your Google/GitHub account email **before** running `migrate --seed`.

Visit `http://localhost:8000` and log in.

---

## Documentation

- [Installation](docs/INSTALLATION.md) — Git, Docker (dev + prod), ZIP / shared hosting
- [Configuration](docs/CONFIGURATION.md) — Environment variables, OAuth setup, GitHub integration, storage, mail
- [FAQ](docs/FAQ.md) — Setup troubleshooting and usage questions

## License

[MIT](LICENSE) — Copyright (c) 2026 Nakul Sri Kuber
```

- [ ] **Step 2: Verify**

```bash
wc -l README.md
```

Expected: under 90 lines.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: rewrite README — concise with links to docs/"
```

---

## Task 9: Push all docs to remote

- [ ] **Step 1: Verify all files exist**

```bash
ls -1 LICENSE Dockerfile docker-compose.yml docker-compose.dev.yml \
  docker/nginx.conf docker/start.sh docker/Dockerfile.dev \
  docs/INSTALLATION.md docs/CONFIGURATION.md docs/FAQ.md README.md
```

Expected: all 11 files listed with no "No such file" errors.

- [ ] **Step 2: Push to remote**

```bash
git push origin main
```

Expected: `main -> main` with commit count matching the tasks above.

---

## Self-Review Checklist

- [x] LICENSE: MIT text, correct name and year
- [x] Dockerfile: PHP 8.3-fpm-alpine, all required extensions, startup script runs migrations + seeder
- [x] docker-compose.yml: app + queue + scheduler, named volumes for persistence
- [x] docker-compose.dev.yml: volume mount for hot-reload, Vite port 5173 exposed
- [x] INSTALLATION.md: all three methods (Git, Docker dev+prod, ZIP), upgrade section
- [x] CONFIGURATION.md: all .env vars from .env.example covered, OAuth walkthroughs, S3, mail
- [x] FAQ.md: covers all setup errors + usage questions from design spec
- [x] README.md: ~80 lines, badges, quick-start, links to all docs files
- [x] ADMIN_EMAIL documented in both CONFIGURATION.md and README quick-start note
- [x] All commits atomic per task
