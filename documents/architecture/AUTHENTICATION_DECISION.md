# Authentication Decision — Module 1 (Implemented)

**Last updated:** 2026-06-15  
**Status:** Implemented  
**Framework:** Laravel 13.15.0

## Principal reference

- [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx)
- [ADR-001-authentication-foundation.md](decisions/ADR-001-authentication-foundation.md)
- [Laravel 13 Starter Kits](https://laravel.com/docs/13.x/starter-kits)
- [Laravel 13 Fortify](https://laravel.com/docs/13.x/fortify)

---

## Implemented approach

**Laravel 13 Livewire Starter Kit equivalent:** Fortify + Livewire 4 + Volt + Flux UI.

The Packagist package `laravel/livewire-starter-kit` currently requires Laravel 12 only. This project implements the same patterns manually in an existing Laravel 13 app (see ADR-001).

### Packages

| Package | Version (approx.) | Purpose |
|---------|-------------------|---------|
| `laravel/fortify` | ^1.37 | Auth backend actions |
| `livewire/livewire` | ^4.3 | UI framework |
| `livewire/volt` | ^1.10 | Single-file components |
| `livewire/flux` | ^2.14 | Tailwind UI kit |

### Capabilities delivered

- Login, logout, password reset, email verification
- CSRF protection (web middleware)
- Session regeneration on login
- Login rate limiting (5/minute per email+IP)
- Remember-me (optional checkbox on login form)
- `guest`, `auth`, `verified` middleware on protected dashboards
- Fortify `views` disabled; Volt routes in `routes/auth.php`

---

## Registration policy (B2B managed SaaS)

**Public registration is disabled.**

- No `/register` route
- Fortify `Features::registration()` not enabled
- New users are created by platform super-admins (tenant owner provisioning) or the local `platform:create-super-admin` command
- Tenants start as `pending` until platform activation

## Super-admin bootstrap security

```bat
D:\php83\php.exe artisan platform:create-super-admin
```

- Password via **hidden interactive prompt** with confirmation (no `--password` CLI flag)
- Optional local-only `PLATFORM_BOOTSTRAP_PASSWORD` environment variable (must be unset after bootstrap)
- Command blocked in `production`
- Never commit passwords to Git or documentation

---

## Post-login routing

| User type | Redirect |
|-----------|----------|
| Platform super-admin | `/platform/tenants` |
| Single active tenant membership | `/app/{tenant_uuid}/dashboard` |
| Multiple memberships | `/app/select-tenant` |
| No tenant access | `/` (home) |

---

## Explicitly not implemented (Module 1)

- Laravel Breeze (`breeze:install livewire`) — obsolete terminology; not used
- React, Vue, Svelte, Inertia
- Social login, passkeys, SSO, WorkOS
- Two-factor authentication (deferred)

---

## Authentication contexts (unchanged from architecture)

```
Platform: /platform/*  → users.platform_role = super_admin
Tenant:   /app/{uuid}/* → tenant_user membership + active tenant
```

Never trust client-submitted `tenant_id`.
