# Module 1 — Multi-tenant schema and design

**Last updated:** 2026-06-15 (corrective pass)  
**Status:** Implemented (Module 1)

## Tables

### `users` (extended)

| Column | Purpose |
|--------|---------|
| `uuid` | Public user identifier |
| `platform_role` | `super_admin` or null |
| `status` | `active`, `disabled` |
| `last_login_at` | Last successful login |

### `tenants`

| Column | Purpose |
|--------|---------|
| `uuid` | Public route identifier (never numeric `id` in URLs) |
| `slug` | Unique organisation slug |
| `status` | `pending`, `active`, `suspended`, `cancelled` |
| `activated_at`, `suspended_at`, `suspension_reason` | Lifecycle |
| `created_by` | Platform actor |

### `tenant_user`

Membership pivot (not `users.tenant_id`).

| Column | Purpose |
|--------|---------|
| `role` | `owner`, `admin`, `staff` |
| `status` | `invited`, `active`, `inactive` |
| `is_owner` | Initial owner flag |
| `joined_at` | Membership start |

**Constraints:** `UNIQUE (tenant_id, user_id)`; FK `RESTRICT` on delete.

### `audit_logs`

Append-only security events. Metadata includes `actor_scope` (`platform` / `tenant`), `before`, `after`, and `target_user_id` for membership changes. No passwords/tokens in metadata.

### `tenant_notes`

Sample tenant-owned table for isolation tests. Uses `BelongsToTenant`.

## Membership lifecycle

All membership mutations go through `App\Services\Tenancy\MembershipLifecycleService`:

| Operation | Audit action | Authorization |
|-----------|--------------|---------------|
| Add member | `membership.created` | Platform super-admin or tenant owner/admin |
| Change role | `membership.role_changed` | Policy + role hierarchy |
| Change status | `membership.status_changed` | Policy + final-owner rule |
| Remove member | `membership.removed` | Policy + final-owner rule |

### Final active owner protection

The last active owner (`role = owner` or `is_owner = true`) cannot be:

- deactivated (`status → inactive`)
- demoted to another role
- removed

unless another active owner exists (ownership transfer deferred to later module).

### Role assignment rules

| Actor | May assign |
|-------|------------|
| Platform super-admin | Any tenant role including owner |
| Tenant owner | `admin`, `staff` (not owner) |
| Tenant admin | `staff` only |
| Tenant staff | None |

Platform users (`users.platform_role`) cannot be modified through tenant membership operations.

## Tenant context lifecycle

1. **Request start:** `ClearTenantContext` middleware clears all tenant state.
2. **Tenant route (`/app/{uuid}/*`):** `ResolveTenant` validates auth, membership, and tenant status; sets context; calls `enforceIsolation()`.
3. **During request:** `BelongsToTenant` scopes queries to resolved tenant; fail-closed (`WHERE 1=0`) when isolation enforced but no tenant resolved.
4. **Request end:** `ResolveTenant::terminate()` and `ClearTenantContext::terminate()` clear context (success, 403, 404, validation failure, or exception).
5. **Platform routes (`/platform/*`):** Never set tenant context; cannot inherit prior tenant state.
6. **Tests:** `TestCase::tearDown()` clears context between tests.
7. **Future jobs/CLI:** Must explicitly initialize and clear tenant context; no implicit inheritance.

## Global scope bypass policy

| Context | Behaviour |
|---------|-----------|
| Tenant route with enforced isolation | Scoped queries; `tenant_id` forced on create; updates cannot change `tenant_id` |
| Platform routes | Isolation not enforced; platform queries use explicit tenant filters |
| Factories/seeders | Use `forceCreate()` or set context deliberately |
| Platform bypass of tenant data | Super-admin via `ResolveTenant` platform bypass; sensitive bypasses should be audited |

## Roles

| Scope | Roles |
|-------|-------|
| Platform | `super_admin` (`users.platform_role`) |
| Tenant | `owner`, `admin`, `staff` (`tenant_user.role`) |

## Status rules

| Tenant status | Tenant dashboard access |
|---------------|-------------------------|
| `pending` | Denied |
| `active` | Allowed for active members |
| `suspended` | Denied (data preserved) |
| `cancelled` | Denied |

| Membership status | Access |
|-------------------|--------|
| `active` | Allowed (if tenant active) |
| `inactive`, `invited` | Denied |

| User status | Access |
|-------------|--------|
| `active` | Allowed per policies |
| `disabled` | Logged out; all protected routes denied |

## Bootstrap

```bat
D:\php83\php.exe artisan platform:create-super-admin
```

- **No `--password` CLI option** (prevents shell history exposure)
- Password entered via hidden prompt with confirmation
- Optional temporary `PLATFORM_BOOTSTRAP_PASSWORD` env var for local bootstrap only (unset after use)
- Blocked in `production`

## HTTP smoke verification

1. Start: `D:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000`
2. Verify unauthenticated: `/` and `/login` return 200
3. Log in via Livewire login form at `/login`
4. Verify platform super-admin: `/platform/tenants`
5. Log in as tenant member; verify `/app/{uuid}/dashboard`, `/members`, `/notes`
6. Verify unauthorized: tenant user → `/platform/tenants` (403); cross-tenant UUID (403)

Automated equivalent: `tests/Feature/AuthenticatedHttpSmokeTest.php`

## Fortify 2FA / passkey schema

Migrations `add_two_factor_columns_to_users_table` and `create_passkeys_table` were published by `fortify:install` for starter-kit compatibility. Features are **disabled** in `config/fortify.php` (no registration, 2FA, or passkeys). No routes expose these endpoints. Schema retained to avoid drift from future Fortify upgrades; can be removed via ADR if desired.

## Deferred to later modules

- Plans, subscriptions, billing, entitlements
- Custom domains / subdomain provisioning
- Ownership transfer workflow UI
- 2FA, passkeys, SSO
- AI, widget, leads, Redis, Reverb
