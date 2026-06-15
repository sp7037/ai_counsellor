# Technology Decisions

**Last updated:** 2026-06-15 (Phase 0B — blocked)

## Principal reference

See [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) for full stack rationale.

---

## Current runtime (unchanged — Phase 0B blocked)

| Component | Current version | Status |
|-----------|-----------------|--------|
| PHP | 8.0.30 (`D:\xampp\php\php.exe`) | **EOL — blocker** |
| Laravel | 9.52.21 | **EOL — must replace** |
| Composer | 2.10.1 (`D:\xampp\php\composer`) | OK |
| MariaDB | 10.4.32 (XAMPP) | OK locally; not running during audit |
| Node.js | 22.22.0 | OK for Vite |
| npm | 10.2.4 | OK |
| Frontend build | Laravel Mix 6 | **Obsolete — must replace with Vite** |

## Planned runtime (after Phase 0B)

| Component | Target version | Reason |
|-----------|----------------|--------|
| PHP | **8.3.x or 8.4.x** | Supported release; Laravel 12 requirement |
| Laravel | **12.x** (latest stable patch) | New project; security support; Vite included |
| Frontend | **Vite** | Official modern Laravel asset pipeline; works with Node 22 |
| Livewire | 3.x (Module 1) | Compatible with Laravel 11/12; install later |
| MariaDB | 10.4+ local | MySQL-compatible schema only |
| Redis / Reverb | Production VPS | Not required for Phase 0B |

## Reason for modernisation (Phase 0B)

| Problem | Impact |
|---------|--------|
| PHP 8.0 EOL | No security patches |
| Laravel 9 EOL | 13 `composer audit` advisories |
| Laravel Mix + Node 22 | Build fails (`webpack/lib/SizeFormatHelpers`) |
| Missing `intl` extension | Blocks Laravel 10+ |
| No business code yet | Safest time for clean skeleton rebuild |

## PHP executable audit (2026-06-15)

Only one PHP installation found:

```text
D:\xampp\php\php.exe → PHP 8.0.30
```

Searched without result: `D:\php`, `D:\php83`, `D:\php84`, `C:\php`, Laragon, WAMP.

**Phase 0B did not proceed.** See [PHP_UPGRADE_GUIDE.md](../setup/PHP_UPGRADE_GUIDE.md).

## Laravel version selection (planned)

When PHP 8.3+ is available:

1. Verify latest **stable** Laravel 12.x on https://laravel.com/docs
2. Confirm compatibility: Blade, Vite, Breeze, Livewire 3, queues, Redis, Reverb
3. Use clean `create-project` — do not multi-hop upgrade from Laravel 9
4. If Laravel 12 has unexpected blockers, fall back to latest stable Laravel 11.x with documented ADR

Do not use beta, RC, or nightly releases.

## Composer execution

Composer is not on system PATH:

```powershell
<PHP83_PATH>\php.exe D:\xampp\php\composer <command>
```

Example after PHP upgrade:

```powershell
D:\xampp\php\php.exe D:\xampp\php\composer create-project laravel/laravel:^12.0 temp --prefer-dist
```

## PHP extensions required

| Extension | PHP 8.0 status | Required |
|-----------|----------------|----------|
| ctype, curl, dom, fileinfo, filter, hash, mbstring, openssl, pcre, pdo, pdo_mysql, session, tokenizer, xml, zip, bcmath | Present | Yes |
| intl | **Missing** | Yes |

## Stack decisions (unchanged intent)

| Layer | Decision |
|-------|----------|
| Database | MariaDB/MySQL; tenant-scoped tables |
| Admin UI | Blade + Livewire (Module 1) |
| Public widget | Vanilla JavaScript (Module 2) |
| Local dev | XAMPP on Windows |
| Production | VPS/cloud primary; cPanel limited |

## Packages not installed

- Livewire, Reverb, Breeze, tenancy packages, permissions packages
- OpenAI, payments, WhatsApp, React, Vue

## Environment configuration (current Laravel 9)

| Setting | Value |
|---------|-------|
| Application timezone | `Asia/Kolkata` (`config/app.php`) |
| Application locale | `en` |
| Local URL | `http://localhost/ai_counsellor/public` |
| Database (prepared) | `ai_counsellor` — not created |

## Authentication (Module 1 plan)

Laravel **Breeze (Blade stack)** — see [AUTHENTICATION_DECISION.md](AUTHENTICATION_DECISION.md).
