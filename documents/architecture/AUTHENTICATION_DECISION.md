# Authentication Decision — Module 1 (Planned, Not Implemented)

**Last updated:** 2026-06-15 (Phase 0B corrective pass)  
**Status:** Documented only — no authentication code installed yet  
**Framework:** Laravel 13.15.0

## Principal reference

- [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §5 (roles), §14 (security)
- [SECURITY_BASELINE.md](SECURITY_BASELINE.md)
- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) — Module 1 plan
- [Laravel 13 Starter Kits](https://laravel.com/docs/starter-kits)

---

## Architecture requirement

The master architecture specifies **Blade + Livewire** for tenant and platform admin dashboards. The authentication foundation must:

- Use official or first-party Laravel-compatible scaffolding
- Support Livewire-based admin UI
- **Not** introduce React, Vue, Svelte, or Inertia application architecture
- Remain maintainable on Laravel 13

---

## Laravel 13 official options (verified)

| Option | Status for this project | Assessment |
|--------|-------------------------|------------|
| **Laravel Livewire Starter Kit** | Official Laravel 13 recommendation | Livewire 4 + Flux UI + Tailwind 4; designed for `laravel new --livewire` |
| **Laravel Breeze — `livewire` stack** | **Recommended for Module 1** | Official package; `php artisan breeze:install livewire`; Laravel 13 compatible; lighter than full starter-kit import |
| Laravel Breeze — `blade` stack | Not recommended as primary | Blade + Alpine only; does not establish Livewire dashboards required by architecture |
| Laravel Jetstream | Not recommended | Heavier; teams/2FA features not needed in Module 1 MVP |
| Fortify-only | Not recommended | Headless; excessive custom UI work for Blade + Livewire goal |
| React / Vue / Svelte / Inertia kits | **Excluded** | Conflicts with architecture |

Breeze remains officially maintained for Laravel 13 (including `livewire` and `livewire-functional` stacks). Laravel 13 also documents dedicated starter kits via `laravel new`, but this project was scaffolded without a starter kit in Phase 0B.

---

## Recommended Module 1 approach

### Primary: **Laravel Breeze with Livewire stack**

Install in Module 1 only (not Phase 0B):

```bat
D:\php83\php.exe D:\xampp\php\composer require laravel/breeze --dev
D:\php83\php.exe artisan breeze:install livewire
```

| Attribute | Value |
|-----------|-------|
| Package | `laravel/breeze` |
| Stack | `livewire` |
| Provides | Login, logout, password reset, email verification hooks, profile basics |
| UI | Blade layouts + Livewire components |
| Compatible with | Laravel 13, PHP 8.3+, Livewire 3/4 (per Breeze release) |

### Alternative (if Breeze Livewire proves insufficient)

Adopt authentication and layout patterns from the official **[Laravel Livewire Starter Kit](https://github.com/laravel/livewire-starter-kit)** (Livewire 4 + Flux UI). This is heavier but matches the Laravel 13 homepage default. Requires an ADR if chosen over Breeze.

---

## Separation of concerns (Module 1 scope)

| Layer | Module | Phase 0B | Module 1 implementation |
|-------|--------|----------|-------------------------|
| **Authentication scaffolding** | Login, logout, password reset, session | Not installed | Breeze `livewire` stack |
| **Authorization / roles** | Permission checks | Not installed | Custom `roles`, `permissions`, policies |
| **Tenant membership** | `tenant_user` pivot, tenant resolution | Not installed | Migrations + middleware |
| **Platform super-admin** | `/platform/*` context | Not installed | `is_platform_user`, separate routes |

Phase 0B installs **none** of the above.

---

## Planned authentication architecture

### Two authentication contexts

```
┌─────────────────────────────────────────────────────────┐
│  Platform context (/platform/*)                         │
│  - users.is_platform_user = true                        │
│  - Middleware: EnsurePlatformAdmin                      │
│  - No tenant_id in session as authority                 │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  Tenant context (/app/{tenant:uuid}/*)                  │
│  - Resolved tenant from route UUID + tenant_user pivot   │
│  - Middleware: ResolveTenant, EnsureTenantMembership    │
│  - Policies verify tenant_id on every resource          │
└─────────────────────────────────────────────────────────┘
```

### Login flows

1. **Platform login** — `GET/POST /platform/login` (separate view or role-based redirect after shared login).
2. **Tenant login** — Breeze login → redirect to tenant selector or default tenant dashboard.
3. **No public self-registration** — registration routes disabled or not published; users invited by platform or tenant admin.

### Tenant resolution (post-login)

1. User authenticates globally (`users` table).
2. Application loads `tenant_user` memberships from database.
3. If one tenant → redirect to `/app/{tenant_uuid}/dashboard`.
4. If multiple → tenant picker (server-side membership list only).
5. Route model binding uses `tenant.uuid`, never numeric `id` in URLs.
6. **Never trust browser-submitted `tenant_id`.**

### Session, CSRF, and rate limiting

- Laravel `web` middleware (CSRF, encrypted cookies).
- Production: `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax` or `strict`.
- Rate-limit login and password reset via `RateLimiter`.

### Email verification and 2FA

- Email verification: configurable via `MustVerifyEmail` when policy requires.
- Two-factor authentication: deferred; plan Fortify 2FA or equivalent in later security phase.

---

## Permissions (Module 1 — not Phase 0B)

- Custom `roles`, `permissions`, `role_permission` tables per architecture.
- No `spatie/laravel-permission` unless approved via ADR.
- Policies and middleware enforce permissions; never rely on hidden form fields.

---

## What is explicitly not implemented in Phase 0B

- No Breeze or Livewire Starter Kit installation
- No login routes beyond Laravel defaults
- No tenant, role, or permission tables
- No platform admin UI

---

## Implementation trigger

Install authentication **only when**:

1. Phase 0B gates are complete (PHP 8.3+, Laravel 13, Vite, clean audits).
2. Module 1 development begins.
3. First migrations for platform user fields and `tenant_user` are ready.
