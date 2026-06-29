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
