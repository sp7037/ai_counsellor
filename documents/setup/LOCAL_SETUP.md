# Local Setup (XAMPP)

**Last updated:** 2026-06-15 (Phase 0)

## Prerequisites

| Requirement | Expected value |
|-------------|----------------|
| XAMPP path | `D:\xampp` |
| PHP | 8.0.30+ (CLI: `D:\xampp\php\php.exe`) |
| MariaDB | 10.4.x (XAMPP) |
| Composer | `D:\xampp\php\composer` |
| Node.js | 18+ recommended (22.x installed) |

## Project location

```
D:\xampp\htdocs\ai_counsellor
```

## Local URL

```
http://localhost/ai_counsellor/public
```

Ensure Apache is running in XAMPP Control Panel.

## Initial setup steps

### 1. Clone or open project

Project root must contain `artisan`, `composer.json`, and `public/index.php`.

### 2. Install PHP dependencies

```powershell
cd D:\xampp\htdocs\ai_counsellor
php D:\xampp\php\composer install
```

### 3. Environment file

Copy example if `.env` does not exist:

```powershell
copy .env.example .env
php artisan key:generate
```

Configured local values:

| Variable | Value |
|----------|-------|
| `APP_NAME` | AI Counsellor |
| `APP_URL` | http://localhost/ai_counsellor/public |
| `DB_DATABASE` | ai_counsellor |
| `DB_USERNAME` | root |
| `DB_PASSWORD` | (empty, default XAMPP) |
| `LOG_LEVEL` | debug |

Timezone is set in `config/app.php` to `Asia/Kolkata`. Locale defaults to `en`.

### 4. Create database (manual)

XAMPP MariaDB does not auto-create the application database. In phpMyAdmin (`http://localhost/phpmyadmin`) or CLI:

```sql
CREATE DATABASE ai_counsellor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then run migrations when Module 1 delivers them:

```powershell
php artisan migrate
```

### 5. Install frontend dependencies (optional for Phase 0)

```powershell
npm install
npm run production
```

Laravel 9 uses Laravel Mix; the production script is `npm run production` (there is no `npm run build` script).

**Known issue (2026-06-15):** Node.js 22.x may be incompatible with the default Laravel 9 `laravel-mix@6` / webpack toolchain (`Cannot find module 'webpack/lib/SizeFormatHelpers'`). Frontend asset compilation is not required for Phase 0. Options for a later fix:

- Use Node 18 LTS via `nvm` for this project, or
- Upgrade to Vite when moving to Laravel 10+.

Or for development with hot reload (same Node caveat may apply):

```powershell
npm run dev
```

### 6. Verify installation

```powershell
php artisan --version
php artisan about
php artisan test
```

Open `http://localhost/ai_counsellor/public` — expect HTTP 200 and Laravel welcome page.

## Composer not on PATH

Use the full invocation:

```powershell
php D:\xampp\php\composer install
php D:\xampp\php\composer update
```

## Storage permissions

On Windows/XAMPP, ensure `storage/` and `bootstrap/cache/` are writable by the web server user.

```powershell
php artisan storage:link
```

## Local services (Phase 0 defaults)

| Service | Local setting |
|---------|---------------|
| Cache | `file` |
| Queue | `sync` |
| Session | `file` |
| Broadcast | `log` |

Redis is not required locally until queue/cache features are developed. Production uses Redis (see [VPS_PRODUCTION_REQUIREMENTS.md](VPS_PRODUCTION_REQUIREMENTS.md)).

## Troubleshooting

| Issue | Resolution |
|-------|------------|
| 404 on homepage | Use `/public` URL or configure Apache virtual host document root to `public/` |
| `composer` not found | Use `php D:\xampp\php\composer` |
| Database connection refused | Start MySQL in XAMPP Control Panel |
| `SQLSTATE[1049]` unknown database | Create `ai_counsellor` database manually |
| APP_KEY missing | Run `php artisan key:generate` |

## Security reminder

- Never commit `.env`
- Use empty root password only for local XAMPP; use strong credentials in production
