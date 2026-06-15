# Local Setup (XAMPP + PHP 8.3)

**Last updated:** 2026-06-15 (Module 2 — complete)

## Stack

| Component | Version | Notes |
|-----------|---------|-------|
| PHP (project) | 8.3.31 | `D:\php83\php.exe` |
| PHP (XAMPP, other projects) | 8.0.30 | `D:\xampp\php\php.exe` — **not used for AI Counsellor** |
| Laravel | 13.15.0 | |
| MariaDB | 10.4.32 | XAMPP — port **3310** |
| Node.js (Vite builds) | 22.22.0 | See Node section below |
| Composer | 2.10.1 | Via PHP 8.3 |

## Project location

```
D:\xampp\htdocs\ai_counsellor
```

## Local development URL

```
http://127.0.0.1:8000
```

Start the server:

```bat
cd /d D:\xampp\htdocs\ai_counsellor
D:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000
```

### Why not Apache / `localhost/ai_counsellor/public`?

XAMPP Apache is configured for PHP 8.0.30. This project requires PHP 8.3.31. Using `artisan serve` with `D:\php83\php.exe` avoids modifying XAMPP Apache or affecting other projects.

Optional future step: configure a separate Apache vhost pointing to PHP 8.3 — requires explicit owner permission.

---

## Prerequisites

1. PHP 8.3.31 at `D:\php83\` (see [PHP_UPGRADE_GUIDE.md](PHP_UPGRADE_GUIDE.md))
2. XAMPP MySQL/MariaDB running (XAMPP Control Panel)
3. Node.js 22.12+ for Vite builds
4. Composer PHAR at `D:\xampp\php\composer`

---

## Initial setup

### 1. PHP dependencies

```bat
cd /d D:\xampp\htdocs\ai_counsellor
D:\php83\php.exe D:\xampp\php\composer install
```

### 2. Environment

Copy if needed:

```bat
copy .env.example .env
D:\php83\php.exe artisan key:generate
```

Key `.env` values:

| Variable | Value |
|----------|-------|
| `APP_NAME` | AI Counsellor |
| `APP_URL` | http://127.0.0.1:8000 |
| `APP_TIMEZONE` | Asia/Kolkata |
| `DB_PORT` | **3310** |
| `DB_DATABASE` | ai_counsellor |
| `DB_USERNAME` | root |
| `DB_PASSWORD` | (empty) |

### 3. Database

Ensure MariaDB is running, then create database if missing:

```sql
CREATE DATABASE IF NOT EXISTS ai_counsellor
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Connect via CLI:

```bat
D:\xampp\mysql\bin\mysql.exe -u root -P 3310
```

Run migrations:

```bat
D:\php83\php.exe artisan migrate
```

### 4. Frontend assets (Vite)

```powershell
$node = "C:\Program Files\cursor\resources\app\resources\helpers\node.exe"
$npm = "C:\nvm4w\nodejs\node_modules\npm\bin\npm-cli.js"
& $node $npm install
& $node $npm run build
```

**Node version:** Vite 8 requires Node `^20.19.0` or `>=22.12.0`. The nvm default (`C:\nvm4w\nodejs`) is Node 20.11.1 — invoke npm through Node 22 as shown above.

### Rolldown platform binding (Windows local workaround)

Vite 8 uses `rolldown`, which installs platform bindings as optional dependencies. On Windows, npm may fail to install the binding due to a known optional-dependency bug.

This project declares in `package.json`:

```json
"optionalDependencies": {
  "@rolldown/binding-win32-x64-msvc": "1.0.3"
}
```

| Platform | Binding installed |
|----------|-------------------|
| Windows (local) | `@rolldown/binding-win32-x64-msvc` |
| Linux VPS (production build) | `@rolldown/binding-linux-x64-gnu` via `rolldown` — automatic |

This Windows entry is a **local dev workaround only**, not a production Linux requirement.

If a clean install fails on Windows:

```powershell
Remove-Item -Recurse -Force node_modules
Remove-Item package-lock.json
& $node $npm install --include=optional
```

### 5. Verify

```bat
D:\php83\php.exe artisan --version
D:\php83\php.exe artisan test
D:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000` — expect HTTP 200.

---

## Super-admin bootstrap (local only)

```bat
D:\php83\php.exe artisan platform:create-super-admin
```

Interactive hidden password prompt with confirmation. **Do not pass passwords on the command line.**

Optional temporary local bootstrap (unset immediately after use):

```bat
set PLATFORM_BOOTSTRAP_PASSWORD=your-local-only-password
D:\php83\php.exe artisan platform:create-super-admin --name="Admin" --email=admin@example.test
set PLATFORM_BOOTSTRAP_PASSWORD=
```

Log in at `/login`, then manage tenants at `/platform/tenants`.

### HTTP smoke verification

1. `D:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000`
2. Open `http://127.0.0.1:8000/` and `/login` (expect 200)
3. Authenticate via the Livewire login form
4. Verify platform (`/platform/tenants`) and tenant (`/app/{uuid}/dashboard`) pages
5. Automated: `D:\php83\php.exe artisan test --filter=AuthenticatedHttpSmokeTest`

---

## Composer command reference

```bat
D:\php83\php.exe D:\xampp\php\composer install
D:\php83\php.exe D:\xampp\php\composer update
D:\php83\php.exe D:\xampp\php\composer audit
```

Never use `D:\xampp\php\php.exe` for this project.

---

## Module 2 widget (local)

After `npm run build`, the embed script is at `public/build/widget.js`.

1. Log in as tenant owner/admin → **Chat widget** (`/app/{tenant_uuid}/widget`)
2. Create a widget key and add/verify domain `127.0.0.1` (or rely on `WIDGET_ALLOW_LOCAL_ORIGINS=true`)
3. Open demo pages:
   - `http://127.0.0.1:8000/widget-demo/static.html`
   - `http://127.0.0.1:8000/widget-demo/php/`
   - `http://127.0.0.1:8000/widget-demo/wordpress.html`
4. Replace `YOUR_WIDGET_KEY` in the demo HTML with your `wk_…` key

Optional `.env` overrides:

| Variable | Default | Purpose |
|----------|---------|---------|
| `WIDGET_ALLOW_LOCAL_ORIGINS` | unset (fail-closed outside local/testing) | Set `true` only for local embed testing |
| `WIDGET_SESSION_TTL_MINUTES` | `120` | Widget session lifetime |

Gateway API base: `http://127.0.0.1:8000/widget/v1`

**Production:** set `WIDGET_ALLOW_LOCAL_ORIGINS=false` and verify all embed domains in the tenant widget admin UI.

## Module 3 configuration (local)

Tenant owners/admins configure branding, assistant, office hours and catalogues at `/app/{tenant_uuid}/configuration`.

After `php artisan storage:link`, uploaded logos are served from `public/storage/tenant-logos/…`.

---

## Backup locations

| Item | Path |
|------|------|
| Phase 0 backup | `D:\xampp\htdocs\ai_counsellor_phase0_backup` |
| Git baseline | commits `776a25b`, `f89128b` |

---

## Troubleshooting

| Issue | Resolution |
|-------|------------|
| MySQL connection refused | Start MySQL in XAMPP Control Panel |
| Wrong port | XAMPP MariaDB uses port **3310**, not 3306 |
| Vite build fails (Node version) | Use Node 22.12+ |
| Vite rolldown binding error | Run `npm install` with Node 22; binding package is in devDependencies |
| Apache shows old Laravel/PHP errors | Use `artisan serve` with PHP 8.3 instead |

## Security reminder

- Never commit `.env`
- Local empty root password is for development only
