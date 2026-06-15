# Authentication Decision — Module 1 (Planned, Not Implemented)

**Last updated:** 2026-06-15 (Phase 0B)  
**Status:** Documented only — no authentication code installed yet

## Principal reference

- [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §5 (roles), §14 (security)
- [SECURITY_BASELINE.md](SECURITY_BASELINE.md)
- [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) — Module 1 plan

---

## Requirements summary

| Requirement | Approach |
|-------------|----------|
| Platform super-admin access | Separate guard/context; `/platform/*` routes |
| Tenant user authentication | Standard session auth scoped to tenant membership |
| Public self-registration | **Disabled initially** — invite-only tenant users |
| Password reset | Supported via Laravel password reset flow |
| Email verification | Configurable per tenant/platform policy |
| Rate-limited login | Laravel `RateLimiter` on login and password reset |
| Session security | Encrypted cookies; secure/HTTP-only/SameSite in production |
| Future 2FA | Plan for TOTP; not in Module 1 MVP |
| Policies and permissions | Laravel policies + permission checks (custom tables, not package yet) |
| Tenant selection | **Never trust browser-submitted `tenant_id`** — resolve from membership or route binding |

---

## Recommended official Laravel method

For the **modernised Laravel 12** foundation (post Phase 0B), use:

### **Laravel Breeze (Blade stack)**

| Attribute | Value |
|-----------|-------|
| Package | `laravel/breeze` |
| Stack | Blade + optional Alpine.js (no React/Vue) |
| Install (Module 1) | `php artisan breeze:install blade` |
| Provides | Login, logout, password reset, email verification hooks, profile basics |
| Livewire compatibility | Breeze Blade views work alongside Livewire 3 components added in Module 1 |

### Why Breeze (not Fortify-only, not Jetstream)

| Option | Assessment |
|--------|------------|
| **Breeze** | Minimal, official, Blade-native, easy to split platform vs tenant login views |
| Fortify-only | Headless — more work for Blade/Livewire dashboards |
| Jetstream | Includes teams/Livewire stacks we do not need; heavier than required |
| UI scaffolding packages | Avoid third-party auth unrelated to Laravel ecosystem |

Install Breeze in **Module 1 only**, after Phase 0B completes.

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

1. **Platform login** — `GET/POST /platform/login` (separate view or shared with role redirect).
2. **Tenant login** — `GET/POST /login` → after auth, redirect to tenant selector or default tenant dashboard.
3. **No public registration** — `RegisterController` routes not published; users created by platform or tenant admin invite.

### Tenant resolution (post-login)

1. User authenticates globally (`users` table).
2. Application loads `tenant_user` memberships.
3. If one tenant → redirect to `/app/{tenant_uuid}/dashboard`.
4. If multiple → show tenant picker (server-side list from membership only).
5. Route model binding uses `tenant.uuid`, never numeric `id` in URLs.

### Session and CSRF

- Default Laravel `web` middleware group (CSRF, session, cookie encryption).
- Production: `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax` or `strict`.

### Rate limiting

```php
RateLimiter::for('login', fn (Request $request) =>
    Limit::perMinute(5)->by($request->input('email').$request->ip())
);
```

Apply to platform and tenant login routes.

### Password reset

- Use Breeze password reset views and `password_reset_tokens` table (Laravel default).
- Rate-limit `password.email` and `password.update` routes.

### Email verification

- Implement `MustVerifyEmail` on `User` when tenant/platform policy requires it.
- Platform super-admin accounts: verification required by default.
- Tenant users: configurable via `tenant_settings`.

### Future two-factor authentication (deferred)

- Plan: Laravel Fortify 2FA or `pragmarx/google2fa` with middleware `EnsureTwoFactorVerified`.
- Required for platform super-admin in a later security hardening phase.

---

## Permissions (Module 1)

- Custom `roles`, `permissions`, `role_permission` tables (per architecture).
- No `spatie/laravel-permission` in initial Module 1 unless ADR approves — keep control explicit.
- Check permissions in policies and middleware: `$user->hasPermission('tenant.users.manage')`.

---

## What is explicitly not implemented in Phase 0B

- No Breeze installation
- No login routes beyond Laravel defaults
- No tenant tables
- No platform admin UI

---

## Implementation trigger

Install authentication **only when**:

1. Phase 0B is complete (PHP 8.3+, Laravel 12, Vite).
2. Module 1 development begins.
3. First migrations for `users` platform fields and `tenant_user` are ready.
