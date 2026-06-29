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
