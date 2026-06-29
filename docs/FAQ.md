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
