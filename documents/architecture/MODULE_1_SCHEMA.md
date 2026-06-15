# Module 1 — Multi-tenant schema and design

**Last updated:** 2026-06-15  
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

Append-only security events. No passwords/tokens in `metadata`.

### `tenant_notes`

Sample tenant-owned table for isolation tests and future patterns. Uses `BelongsToTenant`.

## Tenant context lifecycle

1. Request enters `/app/{tenant:uuid}/*` route group.
2. `ResolveTenant` middleware clears prior context, resolves tenant by UUID, validates membership and tenant status.
3. `TenantContext` service exposes tenant id to `BelongsToTenant` global scope and creating hooks.
4. Context cleared at start of each request; tests must set context explicitly when bypassing HTTP.

Platform routes (`/platform/*`) do **not** set tenant context unless a super-admin explicitly opens a tenant area.

## Roles

| Scope | Roles |
|-------|-------|
| Platform | `super_admin` (`users.platform_role`) |
| Tenant | `owner`, `admin`, `staff` (`tenant_user.role`) |

Platform super-admins manage tenants without becoming ordinary members.

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

## Isolation strategy

- `BelongsToTenant` trait: global scope + forced `tenant_id` on create from `TenantContext`
- `tenant_id` excluded from mass assignment on tenant-owned models
- Route model binding uses `tenants.uuid`
- Policies verify ownership/membership
- Cross-tenant IDOR returns 403/404 without data leakage

## Authorization

Laravel policies: `TenantPolicy`, `TenantMembershipPolicy`, `TenantNotePolicy`  
Middleware: `platform.admin`, `tenant.resolve`, `user.active`

## Bootstrap

```bat
D:\php83\php.exe artisan platform:create-super-admin
```

Disabled in `production` environment. Password via prompt or `--password` (local only).

## Deferred to later modules

- Plans, subscriptions, billing, entitlements
- Custom domains / subdomain provisioning
- Roles/permissions tables beyond fixed enums
- 2FA, passkeys, SSO
- AI, widget, leads, queues beyond defaults
