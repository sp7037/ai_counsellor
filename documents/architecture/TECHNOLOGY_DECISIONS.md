# Technology Decisions

**Last updated:** 2026-06-15 (Module 1 — complete)

## Principal reference

See [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) for full stack rationale.

---

## Current runtime (Phase 0B complete)

| Component | Version | Path / notes |
|-----------|---------|--------------|
| PHP | **8.3.31** | `D:\php83\php.exe` — project CLI only |
| XAMPP PHP | 8.0.30 (unchanged) | `D:\xampp\php\php.exe` — other projects only; **do not use for this project** |
| Laravel | **13.15.0** (skeleton 13.8.0) | Clean install via `create-project` |
| Composer | 2.10.1 | `D:\php83\php.exe D:\xampp\php\composer` |
| MariaDB | 10.4.32 (XAMPP) | Port **3310** (not default 3306) |
| Node.js | 22.22.0 | `C:\Program Files\cursor\resources\app\resources\helpers\node.exe` for Vite builds |
| npm | 10.2.4 | Invoke via Node 22 path (see LOCAL_SETUP.md) |
| Frontend | **Vite 8** + Tailwind 4 | Laravel Mix removed |
| npm audit | **0 vulnerabilities** |
| concurrently | 10.0.3 (devDependency; used by `composer run dev`) |
| shell-quote | 1.8.4 (transitive via concurrently) |
| **livewire/livewire** | ^4.3 (Module 1) |
| **livewire/volt** | ^1.10 (Module 1) |
| **livewire/flux** | ^2.14 (Module 1) |
| **laravel/fortify** | ^1.37 (Module 1) |

## Modernisation summary

| Before (Phase 0) | After (Phase 0B) |
|------------------|------------------|
| PHP 8.0.30 | PHP 8.3.31 |
| Laravel 9.52.21 | Laravel 13.15.0 |
| Laravel Mix 6 | Vite 8 |
| 13 composer security advisories | **0 advisories** (`composer audit`) |
| Apache + XAMPP PHP 8.0 URL | `artisan serve` + PHP 8.3 |

## PHP executable

**Always use for this project:**

```bat
D:\php83\php.exe
```

Configuration: `D:\php83\php.ini`

Enabled during Phase 0B: `extension=zip` (was commented out).

Required extensions verified: bcmath, ctype, curl, dom, fileinfo, filter, hash, intl, mbstring, openssl, pcre, PDO, pdo_mysql, session, tokenizer, xml, zip.

## Composer command

```bat
D:\php83\php.exe D:\xampp\php\composer <command>
```

The Composer PHAR at `D:\xampp\php\composer` is executed by PHP 8.3 — not by XAMPP PHP 8.0.

## Laravel 13 selection rationale

- New project with no business feature code — clean skeleton preferred over multi-major upgrade
- PHP 8.3.31 satisfies Laravel 13 requirements
- Laravel 13 provides current security support, Vite 8, and alignment with master architecture “current supported release”
- `composer audit`: no advisories on installed framework

## Database

| Setting | Value |
|---------|-------|
| Connection | `mysql` |
| Host | `127.0.0.1` |
| Port | **3310** (XAMPP MariaDB custom port) |
| Database | `ai_counsellor` |
| Credentials | `root` / empty (local only) |

Schema uses MySQL-compatible types only (no MariaDB-specific features).

## Local development URL

```
http://127.0.0.1:8000
```

Start with:

```bat
cd /d D:\xampp\htdocs\ai_counsellor
D:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000
```

**Do not use** `http://localhost/ai_counsellor/public` for development — Apache is tied to XAMPP PHP 8.0.

## Frontend (Vite)

| Item | Detail |
|------|--------|
| Build command | `npm run build` |
| Dev command | `npm run dev` |
| Manifest | `public/build/manifest.json` (gitignored; build locally) |

**Node version note:** `C:\nvm4w\nodejs` defaults to Node 20.11.1, which is below Vite 8 minimum. Use Node 22.22.0 for builds. `@rolldown/binding-win32-x64-msvc` is in `optionalDependencies` (Windows local workaround only; Linux VPS uses rolldown's Linux binding).

## Environment configuration

| Setting | Value |
|---------|-------|
| `APP_TIMEZONE` | `Asia/Kolkata` |
| `APP_LOCALE` | `en` |
| `APP_FAKER_LOCALE` | `en_IN` |
| `APP_URL` | `http://127.0.0.1:8000` |
| `LOG_LEVEL` | `debug` (local) |

## Stack decisions (unchanged intent)

| Layer | Decision |
|-------|----------|
| Admin UI | Blade + Livewire 3 (Module 1) |
| Public widget | Vanilla JavaScript (Module 2) — `resources/js/widget/embed.js` → `public/build/widget.js` |
| Cache / queues (local) | Database driver (Laravel 13 default) |
| Production | VPS/cloud; Redis + Reverb |

## Packages not installed

- Livewire, Breeze, Reverb, tenancy packages, permissions packages
- OpenAI, payments, WhatsApp, React, Vue

## Authentication (Module 1 plan)

**Laravel Breeze — `livewire` stack** (official, Laravel 13 compatible, Blade + Livewire). See [AUTHENTICATION_DECISION.md](AUTHENTICATION_DECISION.md).

## Module 1 readiness

**READY FOR MODULE 1** (pending owner review of npm dev advisory noted in IMPLEMENTATION_STATUS).
