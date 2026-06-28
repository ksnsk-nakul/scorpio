# Scorpio — Portfolio CMS

A self-hosted content management system for developers and creative professionals. Manage your portfolio site, track projects, sync GitHub issues as tasks, and control team access — all from a clean admin panel.

Built with Laravel 13, Inertia.js v2, and Vue 3. No password management — Google OAuth only.

---

## Features

### Content & Publishing
- Multi-page site builder with a block-based editor (Hero, Markdown, Service Cards, Gallery, CTA)
- Page templates: Home, About, Services, Contact, Portfolio
- Draft → Published workflow with public preview before publishing
- Service card management with drag-and-drop reorder

### Project & Task Management
- Workspaces to group related projects (client, personal, open-source)
- Projects link to a GitHub repo and GitHub Projects board
- Tasks with status (`open` / `in_progress` / `done` / `closed`) and priority (`low` / `medium` / `high`)
- Unlimited subtask depth via self-referencing `parent_id`
- Threaded comments on tasks and subtasks
- File attachments (images & video) on tasks, subtasks, and comments

### GitHub Integration
- List all repositories from the GitHub API
- Sync open issues → tasks with one click or automatically on an hourly schedule
- Create GitHub Projects and link them to local projects

### Media Library
- Upload images (JPEG, PNG, GIF, WebP, SVG) and video (MP4, WebM, MOV)
- 50 MB default size limit, configurable via the Settings panel
- Polymorphic — attach files to Projects, Tasks, or Comments
- Local disk by default; swap to S3-compatible storage via `.env`

### Auth & Access Control
- Google OAuth — no passwords, no registration forms
- Three roles: **Admin**, **Editor**, **Viewer**
- Role-gated middleware on all admin routes
- Admin panel to reassign roles or remove users

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.4, SQLite |
| Frontend | Inertia.js v2, Vue 3, Tailwind CSS v4, Vite |
| Auth | Laravel Socialite (Google OAuth) |
| RBAC | Spatie Laravel-Permission v8 |
| Testing | Pest v3 — 31 tests, 100% passing |
| Storage | Local disk (S3-swappable via `FILESYSTEM_DISK=s3`) |
| Dev environment | Laravel Herd |

---

## Quick Start

**Prerequisites:** PHP 8.4+, Composer, Node.js 18+, Laravel Herd (or any local server)

```bash
# Clone
git clone git@github.com:ksnsk-nakul/scorpio.git && cd scorpio

# Install dependencies
composer install
npm install

# Environment
cp .env.example .env
php artisan key:generate

# Database
touch database/database.sqlite
php artisan migrate --seed

# Frontend assets
npm run dev
```

### Google OAuth Setup

1. Go to [console.cloud.google.com](https://console.cloud.google.com) → APIs & Services → Credentials
2. Create an **OAuth 2.0 Client ID** (Web application)
3. Add `http://portfolio.test/auth/google/callback` (or your domain) to **Authorized redirect URIs**
4. Copy the credentials into `.env`:

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://portfolio.test/auth/google/callback
```

```bash
php artisan config:clear
```

Visit `http://portfolio.test` — you'll be redirected to the login page.

---

## Default Seeded Data

The database seeder creates:

| Seeder | What it creates |
|---|---|
| `RoleSeeder` | `admin`, `editor`, `viewer` roles with 7 permissions |
| `SettingSeeder` | Default site settings (name, tagline, SEO, media limits) |
| `UserSeeder` | `admin@portfolio.test` user with the `admin` role |

The first Google login assigns the `viewer` role automatically. Promote users to `editor` or `admin` from the Users panel.

---

## GitHub Sync

Add your GitHub token in the **Integrations** panel (group: `github`, key: `token`), then either:

- Click **Sync Issues** on any linked project, or
- Run manually: `php artisan github:sync`
- Runs automatically every hour via the Laravel scheduler:

```bash
# Add to crontab
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Storage (S3)

To switch from local disk to S3-compatible storage, set in `.env`:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
```

---

## Testing

```bash
./vendor/bin/pest
```

All 31 tests pass across feature and unit suites covering auth, media uploads, page builder, service cards, workspaces, projects, tasks, comments, settings, integrations, and users.

---

## Project Structure

```
app/
├── Console/Commands/   SyncGitHubIssues.php
├── Http/Controllers/
│   ├── Admin/          Dashboard, Pages, ServiceCards, Media,
│   │                   Workspaces, Projects, Tasks, Comments,
│   │                   GitHub, Settings, Integrations, Users
│   └── Auth/           GoogleController
├── Models/             User, Workspace, Project, Task, Comment,
│                       Media, Page, ServiceCard, Setting, ThirdPartySetting
├── Policies/           CommentPolicy
└── Services/           GitHubService, MediaService

resources/js/
├── Components/Admin/   BlockEditor, MediaUploader, StatCard
├── Layouts/            AdminLayout
└── Pages/Admin/        Dashboard, Pages, ServiceCards, Workspaces,
                        Projects, Tasks, GitHub, Settings, Integrations, Users
```

---

## Roles & Permissions

| Action | Viewer | Editor | Admin |
|---|:---:|:---:|:---:|
| View dashboard & tasks | ✓ | ✓ | ✓ |
| Create & edit pages | | ✓ | ✓ |
| Manage service cards | | ✓ | ✓ |
| Create projects & tasks | | ✓ | ✓ |
| Manage settings | | | ✓ |
| Manage integrations | | | ✓ |
| Assign user roles | | | ✓ |

---

## License

MIT
