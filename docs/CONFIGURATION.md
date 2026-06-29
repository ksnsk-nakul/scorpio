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
