# Technology Decisions

**Last updated:** 2026-06-15 (Phase 0)

## Principal reference

See [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) for full stack rationale.

## Selected versions (local XAMPP environment)

| Component | Selected version | Reason |
|-----------|------------------|--------|
| PHP | 8.0.30 (XAMPP) | Installed CLI version; Laravel 10+ requires PHP 8.1+ |
| Laravel | 9.x (skeleton 9.0.0, framework 9.52.21) | Latest Laravel line compatible with PHP 8.0.30 |
| Composer | 2.10.1 | Installed to `D:\xampp\php\composer` during Phase 0 |
| MariaDB | 10.4.32 (XAMPP) | Available via XAMPP; MySQL-compatible |
| Node.js | 22.22.0 | Available for frontend asset builds |
| npm | 10.2.4 | Bundled with Node.js |

## Stack decisions

| Layer | Decision | Notes |
|-------|----------|-------|
| Backend | Laravel 9 | Strong auth, queues, policies, validation; matches PHP 8.0 |
| Database | MariaDB / MySQL | Single primary database, tenant-scoped tables (`tenant_id`) |
| Admin UI | Blade + Livewire (planned) | Not installed in Phase 0; install in Module 1 |
| Public widget | Vanilla JavaScript (planned) | Framework-neutral embed; Module 2 |
| Cache / queues (local) | File / sync | Redis planned for VPS production |
| Real-time (production) | Laravel Reverb or fallback | Not configured in Phase 0 |
| AI providers | Adapter layer (planned) | OpenAI first; Module 5 only |
| Local dev | XAMPP on Windows | `D:\xampp` |
| Production target | VPS / cloud (primary) | cPanel as limited secondary |

## Version constraints and upgrade path

### PHP upgrade recommendation

XAMPP currently ships PHP 8.0.30, which is end-of-life. For long-term support and Laravel 10/11:

1. Upgrade XAMPP PHP to **8.2+** when feasible, or
2. Use a separate PHP 8.2+ installation for this project.

Until upgrade, remain on **Laravel 9** and monitor security advisories (`composer audit`).

### Laravel selection rationale

- **Laravel 11** requires PHP 8.2+ — not compatible with current PHP.
- **Laravel 10** requires PHP 8.1+ — not compatible with current PHP.
- **Laravel 9** supports PHP 8.0.2+ — selected.

Composer security blocking prevented installing the latest Laravel 9 skeleton without `--no-security-blocking`. The installed framework resolves to 9.52.21 with known advisories; plan a controlled upgrade after PHP is updated.

### Database

- Local database name (prepared, not auto-created): `ai_counsellor`
- Default XAMPP credentials: `root` with empty password
- Production target: MySQL 8+ or managed compatible MySQL

## Packages intentionally not installed (Phase 0)

- Livewire
- Laravel Reverb
- Redis client packages beyond defaults
- OpenAI / AI SDK packages
- Payment, WhatsApp, or email integration packages
- React or SPA frameworks

## Environment configuration

| Setting | Value |
|---------|-------|
| Application timezone | `Asia/Kolkata` |
| Application locale | `en` |
| Local URL | `http://localhost/ai_counsellor/public` |
| Log level (local) | `debug` via `LOG_CHANNEL=stack` |

## Composer usage on this machine

Composer is not on the system PATH. Use:

```powershell
php D:\xampp\php\composer <command>
```

From the project root:

```powershell
cd D:\xampp\htdocs\ai_counsellor
```
