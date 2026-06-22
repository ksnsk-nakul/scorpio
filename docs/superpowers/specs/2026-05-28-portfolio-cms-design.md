# Portfolio CMS — Design Spec
**Date:** 2026-05-28  
**Status:** Approved

---

## Context

Nakul's current portfolio is a static HTML/CSS/JS site at `/Users/nakul/Sites/Portfolio`. Managing content (projects, services, pages) requires editing HTML by hand. This CMS replaces that with a proper Laravel-backed admin panel, providing a page builder, service card management, GitHub project integration, task/subtask tracking with file attachments, and role-based access control — all driven by a clean Inertia.js + Vue 3 frontend.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 |
| Frontend | Inertia.js + Vue 3 |
| Auth | Laravel Socialite (Gmail OAuth) |
| RBAC | Spatie Laravel-Permission |
| File Storage | Laravel filesystem (local + S3-ready) |
| DB | MySQL (via Laravel Herd) |
| Dev environment | Laravel Herd at `/Users/nakul/Herd/Portfolio` |
| Project name | `portfolio` |

---

## Models & Schema

### User
`id, name, email, avatar, google_id, email_verified_at, timestamps`  
- Google OAuth only (no password login)  
- New registrations assigned `viewer` role by default  
- Admin promotes roles from Users panel

### Role / Permission
Managed by Spatie Laravel-Permission.  
Default roles: `admin`, `editor`, `viewer`

### Workspace
`id, name, slug, description, timestamps`  
A logical grouping of Projects (e.g. "Freelance", "Open Source", "Personal").

### Project
`id, workspace_id, name, slug, description, github_repo (nullable), github_project_id (nullable), cover_image, status (active|archived), sort_order, timestamps`  
- Links to one GitHub repo (owner/repo string) and optionally a GitHub Project
- Has many Tasks, Media

### Task
`id, project_id, parent_id (nullable, self-ref for subtasks), title, body, status (open|in_progress|done|closed), priority, github_issue_id (nullable), github_issue_url (nullable), assignee_id (nullable), due_date, sort_order, timestamps`  
- `parent_id` null = top-level task; non-null = subtask  
- GitHub issues synced here on schedule

### Comment
`id, commentable_type, commentable_id (polymorphic on Task), user_id, body, timestamps`  
- Polymorphic so comments work on tasks and subtasks uniformly

### Media
`id, mediable_type, mediable_id (polymorphic), user_id, disk, path, filename, mime_type, size, alt_text, timestamps`  
- Polymorphic: attachable to Project, Task, Comment  
- Supports image and video MIME types  
- Can be uploaded standalone (project media library) or inline during task/comment creation

### Page
`id, name, slug, template, blocks (JSON), status (draft|published), published_at, timestamps`  
- `blocks`: ordered array of `{type, order, data}` objects  
- Block types: `hero`, `text`, `text_image`, `service_cards`, `project_grid`, `contact_form`
- Templates: `blank`, `hero_cards`, `text_image`, `project_grid`

### ServiceCard
`id, title, description, icon, image, tags (JSON), featured, sort_order, page_id (nullable, links to Page), external_url (nullable), timestamps`

### Setting
`id, key, value, group, timestamps`  
Groups: `general`, `seo`, `social`, `mail`

### ThirdPartySetting
`id, provider, key, value, group, is_active, timestamps`  
Groups: `github`, `google`, `storage`, `analytics`, `other`  
Examples: GitHub token, Google Client ID/Secret, S3 credentials

---

## Feature Areas

### 1. Gmail OAuth Authentication
- Route: `GET /auth/google` → redirect → `GET /auth/google/callback`
- Socialite finds or creates User by google_id/email
- New user → `viewer` role → redirected to dashboard with notice
- Existing user → straight to dashboard

### 2. Dashboard
- Stat cards: Pages, Service Cards, Open Tasks, Users
- Recent Tasks panel (latest 5 across all projects)
- GitHub Repos widget (fetched live from GitHub API via stored token)
- Quick action: New Page, New Task

### 3. Page Builder
- Page list sidebar + template picker
- Block canvas: add, reorder (up/down), delete blocks
- Each block has a type-specific form (title, body, image upload, etc.)
- Publish / Save Draft actions
- Preview opens the rendered page in a new tab (public route)

### 4. Service Card Management
- CRUD with drag-reorder
- Fields: title, description, icon (icon picker or class string), image upload, tags, featured toggle, optional page link or external URL
- Featured cards shown prominently on dashboard

### 5. Workspace & Project Management
- Create/edit Workspaces (name, slug, description)
- Projects belong to a Workspace
- Project settings: link GitHub repo (owner/repo), link GitHub Project (project number/id)
- Project media library: standalone upload of images/videos

### 6. Task & Subtask Tracking
- Tasks list per project, filterable by status/priority/assignee
- Create task: title, body (rich text), status, priority, due date, assignee, file attachments
- Subtasks: created from within a parent task, same fields minus parent
- Comments on any task/subtask with file attachments (inline upload)
- GitHub issue sync: scheduled command pulls open issues from linked repo → creates/updates Tasks

### 7. GitHub Integration
- Store token + target repos in `ThirdPartySetting` (group: `github`)
- `php artisan github:sync` command (scheduled hourly) → syncs issues to Tasks
- Create GitHub Project via API and link to a CMS Project (stored as `github_project_id`)
- Dashboard widget: list repos for the authenticated GitHub user

### 8. File Uploads
- Endpoint: `POST /media` (multipart), returns media record
- Inline: task/comment create/edit forms include a file picker; media IDs passed with the request
- Accepted: images (jpg, png, gif, webp, svg), videos (mp4, mov, webm)
- Max size: 50MB (configurable via Setting)
- Storage: local disk by default; swap to S3 via `.env`

### 9. Roles & Permissions
| Role | Capabilities |
|---|---|
| admin | Everything |
| editor | Manage pages, cards, projects, tasks; cannot manage users/roles |
| viewer | Read-only dashboard access |

### 10. Settings & Integrations
- Settings page: General (site name, tagline), SEO (meta description, OG image), Mail
- Integrations page: manage ThirdPartySettings by group (GitHub, Google, Storage, Analytics)

---

## Key Files to Create

```
app/
  Models/          User, Role, Permission, Setting, Task, ThirdPartySetting,
                   Workspace, Project, Page, ServiceCard, Comment, Media
  Http/Controllers/
    Auth/          GoogleController
    Admin/         DashboardController, PageController, ServiceCardController,
                   WorkspaceController, ProjectController, TaskController,
                   CommentController, MediaController, UserController,
                   SettingController, ThirdPartySettingController
  Jobs/            SyncGitHubIssues
  Console/Commands/ SyncGitHubCommand
  Services/        GitHubService, MediaService
database/
  migrations/      (one per model)
  seeders/         DatabaseSeeder, RoleSeeder, SettingSeeder, UserSeeder
resources/js/
  Pages/Admin/     Dashboard, Pages/*, ServiceCards/*, Projects/*, Tasks/*,
                   Users/*, Settings/*, Integrations/*
  Components/      BlockEditor, MediaUploader, TaskForm, CommentList
routes/
  web.php          auth + admin routes
  api.php          (empty / future)
.env.example       full template with all required keys
```

---

## .env Template Keys

```
APP_NAME=Portfolio
APP_URL=http://portfolio.test

DB_DATABASE=portfolio

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

GITHUB_TOKEN=
GITHUB_USERNAME=

FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
```

---

## Verification

1. `php artisan migrate --seed` — all tables created, default roles/settings seeded
2. Visit `/auth/google` — OAuth redirects to Google and back
3. New user lands on dashboard with `viewer` role
4. Admin creates a page with 3 blocks → publishes → public route renders it
5. Create a project, link a GitHub repo, run `php artisan github:sync` → tasks appear
6. Upload an image to a task → media record created, file saved to storage
7. Role management: promote viewer → editor → verify editor can't access Users panel
