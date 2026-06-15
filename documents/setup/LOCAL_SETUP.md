# Local Setup (XAMPP)

**Last updated:** 2026-06-15 (Phase 0B — blocked)

## ⚠ Phase 0B blocker

**PHP 8.3+ is not installed on this machine.** Phase 0B modernisation (Laravel 12 + Vite) cannot proceed.

**Do not start Module 1** until [PHP_UPGRADE_GUIDE.md](PHP_UPGRADE_GUIDE.md) is completed and Phase 0B is re-run.

Current stack remains **PHP 8.0.30 + Laravel 9.52.21 + Laravel Mix** for reference only.

---

## Prerequisites (target after upgrade)

| Requirement | Current | Target |
|-------------|---------|--------|
| XAMPP path | `D:\xampp` | Same or `D:\xampp84` |
| PHP | 8.0.30 | **8.3+** |
| Laravel | 9.52.21 | **12.x** |
| Frontend | Laravel Mix 6 | **Vite** |
| MariaDB | 10.4.x | 10.4.x+ |
| Composer | `D:\xampp\php\composer` | Same (use new PHP path) |
| Node.js | 22.22.0 | 22.x (OK for Vite) |

## Project location

```
D:\xampp\htdocs\ai_counsellor
```

## Local URL

```
http://localhost/ai_counsellor/public
```

Ensure Apache and MySQL are running in XAMPP Control Panel.

## Backup locations

| Item | Path |
|------|------|
| Phase 0 filesystem backup | `D:\xampp\htdocs\ai_counsellor_phase0_backup` |
| Git baseline | commit `776a25b` |

---

## Current stack setup (Laravel 9 — interim only)

### Install PHP dependencies

```powershell
cd D:\xampp\htdocs\ai_counsellor
php D:\xampp\php\composer install
```

### Environment file

| Variable | Value |
|----------|-------|
| `APP_NAME` | AI Counsellor |
| `APP_URL` | http://localhost/ai_counsellor/public |
| `DB_DATABASE` | ai_counsellor |
| `DB_USERNAME` | root |
| `DB_PASSWORD` | (empty) |
| `LOG_LEVEL` | debug |

Timezone: `Asia/Kolkata` in `config/app.php`. Locale: `en`.

### Create database (manual)

Start MySQL in XAMPP, then:

```sql
CREATE DATABASE ai_counsellor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Migrations not run on modern stack yet. Default Laravel 9 migrations exist but database was not confirmed during Phase 0B (MySQL was stopped).

### Verify current installation

```powershell
php artisan --version
php artisan test
```

Expected: Laravel 9.52.21; 2 tests pass.

### Frontend (current — broken on Node 22)

```powershell
npm install
npm run production   # Laravel 9 / Mix — fails on Node 22
```

After Phase 0B:

```powershell
npm install
npm run build        # Vite — Laravel 12+
```

---

## Composer not on PATH

```powershell
<PHP_PATH>\php.exe D:\xampp\php\composer install
```

After PHP upgrade, replace `<PHP_PATH>` with PHP 8.3+ executable.

## PHPUnit testing note

`phpunit.xml` sets `APP_URL=http://localhost` for tests because subdirectory `APP_URL` breaks feature tests.

## Troubleshooting

| Issue | Resolution |
|-------|------------|
| Phase 0B blocked | Follow [PHP_UPGRADE_GUIDE.md](PHP_UPGRADE_GUIDE.md) |
| 404 on homepage | Use `/public` URL |
| `composer` not found | Use `php D:\xampp\php\composer` |
| MySQL connection refused | Start MySQL in XAMPP Control Panel |
| Mix build fails | Expected on Node 22; resolved by Phase 0B Vite migration |
| Missing `intl` | Enable in `php.ini` on PHP 8.3+ install |

## Security reminder

- Never commit `.env`
- Local XAMPP root password empty is for development only
