# Implementation Status

**Last updated:** 2026-06-15 (Phase 0B — complete)  
**Current phase:** Phase 0B complete; Module 1 not started

## Principal reference

[AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx)

---

## Phase completion summary

| Phase / Module | Status | Notes |
|----------------|--------|-------|
| Phase 0 — Project audit, environment, documentation | **Complete** | Laravel 9 baseline |
| Phase 1A — Laravel foundation installation | **Complete** | Superseded by Phase 0B requirement |
| Phase 0B — Modernise technical foundation | **Complete** | Corrective pass: npm audit clean, auth decision updated |
| Module 1 — SaaS foundation | **Not Started** | Do not begin until Phase 0B completes |
| Module 2 — Embeddable chat widget | **Not Started** | |
| Module 3 — Tenant configuration | **Not Started** | |
| Module 4 — Knowledge base | **Not Started** | |
| Module 5 — AI orchestration | **Not Started** | |
| Module 6 — Lead qualification | **Not Started** | |
| Module 7 — Human agent workspace | **Not Started** | |
| Module 8 — Subscription and usage enforcement | **Not Started** | |
| Later modules | **Not Started** | See [MODULE_ROADMAP.md](MODULE_ROADMAP.md) |

---

## Phase 0 audit results (2026-06-15)

### Project state before work

- Directory contained only `documents/architecture/AI_COUNSELLOR_MASTER_ARCHITECTURE.docx`
- No Laravel installation
- No Git repository
- No database or migrations
- Composer not on system PATH

### Work completed in Phase 0 / 1A

- Environment versions verified and recorded
- Composer 2.10.1 installed to `D:\xampp\php\composer`
- Laravel 9 application installed at project root (preserving `documents/`)
- Application key generated
- `.env` configured for XAMPP local development
- Timezone set to `Asia/Kolkata`; locale `en`
- Agent and architecture documentation created
- Module 1 implementation plan prepared (below)
- Baseline artisan commands and tests executed
- `phpunit.xml` updated with `APP_URL=http://localhost` for testing (subdirectory `.env` URL breaks feature tests)

### Database status

- Database `ai_counsellor` **not auto-created** — credentials prepared in `.env`
- Default Laravel migrations exist but **not run** (no database created yet)

---

## Phase 0B — Modernisation (2026-06-15)

### Status: **COMPLETE**

Phase 0B modernised the technical foundation from Laravel 9 / PHP 8.0 to Laravel 13 / PHP 8.3.31.

### Preservation

| Action | Result |
|--------|--------|
| Filesystem backup | `D:\xampp\htdocs\ai_counsellor_phase0_backup` (retained) |
| Git commits preserved | `776a25b`, `f89128b` |
| `documents/` | Intact |
| Git history | Intact |

### PHP 8.3.31

| Item | Value |
|------|-------|
| Executable | `D:\php83\php.exe` |
| Configuration | `D:\php83\php.ini` |
| Fix applied | Enabled `extension=zip` |
| XAMPP PHP 8.0 | **Untouched** |

### Framework replacement

| Item | Before | After |
|------|--------|-------|
| Laravel | 9.52.21 | **13.15.0** |
| Frontend | Laravel Mix 6 | **Vite 8** |
| Method | Clean `create-project` + skeleton swap | |

### Database (local)

| Item | Status |
|------|--------|
| MariaDB | Running on port **3310** |
| Database `ai_counsellor` | Created |
| Default migrations | **Ran** (users, cache, jobs) |
| Module 1 tables | **Not created** |

### Verification results

| Check | Result |
|-------|--------|
| `php artisan test` | 2 passed |
| `composer audit` | No advisories |
| `npm run build` | Success (`public/build/manifest.json`) |
| HTTP `http://127.0.0.1:8000` | 200 |
| `composer audit` (Laravel 9 baseline) | Was 13 advisories — resolved |

### Local development

Use `D:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000` — not Apache/XAMPP PHP 8.0.

### Remaining non-blocking issues

- Node 22 required for Vite 8; nvm default (`C:\nvm4w\nodejs`) is Node 20.11.1 — use Node 22.12+ for npm commands (see LOCAL_SETUP.md)
- Apache not configured for PHP 8.3 (intentional)
- `npm audit`: **0 vulnerabilities** (resolved: `concurrently@10.0.3` → `shell-quote@1.8.4`)

### Module 1 readiness

**READY FOR MODULE 1**

---

## Module 1 — Implementation Plan (prepared, not implemented)

### Overview

Module 1 delivers the multi-tenant SaaS foundation: platform super-admin access, tenant organisations, domains, users, roles, plans, subscriptions, activation/suspension, feature entitlements, and audit logging. No widget, AI, or lead features in this module.

### 1. Proposed migrations

| Migration | Table | Key columns / notes |
|-----------|-------|---------------------|
| `create_tenants_table` | `tenants` | `id`, `uuid` (public), `name`, `slug`, `status`, `industry_template`, `timezone`, `locale`, `suspended_at`, `suspension_reason`, timestamps, soft deletes |
| `create_tenant_domains_table` | `tenant_domains` | `tenant_id`, `domain`, `verification_status`, `verified_at`, unique `(tenant_id, domain)` |
| `create_tenant_settings_table` | `tenant_settings` | `tenant_id`, JSON `settings` or normalised key-value; branding placeholders |
| `add_platform_fields_to_users_table` | `users` | `is_platform_user`, `platform_role` enum; extend default Laravel users |
| `create_tenant_user_table` | `tenant_user` | `tenant_id`, `user_id`, `role_id`, `status`, `branch`, unique `(tenant_id, user_id)` |
| `create_roles_table` | `roles` | `id`, `tenant_id` (nullable for global roles), `name`, `slug`, `scope` |
| `create_permissions_table` | `permissions` | `id`, `name`, `slug`, `group` |
| `create_role_permission_table` | `role_permission` | `role_id`, `permission_id` |
| `create_plans_table` | `plans` | `id`, `name`, `slug`, `billing_cycle`, `price_minor`, `currency`, `status`, limits JSON |
| `create_subscriptions_table` | `subscriptions` | `tenant_id`, `plan_id`, `status`, `starts_at`, `ends_at`, `grace_ends_at`, `trial_ends_at` |
| `create_feature_entitlements_table` | `feature_entitlements` | `tenant_id`, `feature_key`, `enabled`, `limit_value`, `used_value` |
| `create_audit_logs_table` | `audit_logs` | `id`, `tenant_id` (nullable), `actor_user_id`, `action`, `subject_type`, `subject_id`, `metadata` JSON, `ip_address`, `created_at` |
| `create_platform_settings_table` | `platform_settings` | Optional global config for super-admin |

Rollback: each migration implements `down()` dropping tables/columns in reverse dependency order.

### 2. Proposed models

| Model | Namespace | Notes |
|-------|-----------|-------|
| `Tenant` | `App\Domain\Tenancy\Models` | Global scope helper; status enum |
| `TenantDomain` | `App\Domain\Tenancy\Models` | Domain verification logic |
| `TenantSetting` | `App\Domain\Tenancy\Models` | Cast JSON settings |
| `User` | `App\Models` | Extend Laravel user; platform vs tenant |
| `TenantUser` | `App\Domain\Tenancy\Models` | Pivot model |
| `Role` | `App\Domain\Tenancy\Models` | Tenant-scoped and global roles |
| `Permission` | `App\Domain\Tenancy\Models` | |
| `Plan` | `App\Domain\Billing\Models` | Money as minor units |
| `Subscription` | `App\Domain\Billing\Models` | Status state machine |
| `FeatureEntitlement` | `App\Domain\Billing\Models` | |
| `AuditLog` | `App\Domain\Tenancy\Models` | Append-only |

### 3. Proposed services

| Service | Responsibility |
|---------|----------------|
| `TenantProvisioner` | Create tenant, default settings, initial subscription |
| `TenantSuspensionService` | Suspend, reactivate, record reason, emit audit |
| `SubscriptionService` | Activate, expire, grace transitions, plan changes |
| `EntitlementService` | Resolve effective limits; check feature enabled |
| `AuditLogger` | Central audit write with actor, IP, metadata |
| `DomainVerificationService` | DNS/file verification for tenant domains |
| `PlatformImpersonationService` | Support impersonation with mandatory audit (later) |

### 4. Proposed middleware

| Middleware | Purpose |
|------------|---------|
| `EnsurePlatformAdmin` | Restrict routes to platform super-admin / support |
| `ResolveTenant` | Resolve tenant from authenticated membership |
| `EnsureTenantActive` | Block access if tenant suspended or subscription expired |
| `EnsureTenantMembership` | Verify user belongs to resolved tenant |
| `SetTenantContext` | Bind current tenant to container for scopes |

### 5. Proposed policies

| Policy | Models |
|--------|--------|
| `TenantPolicy` | `Tenant` — platform admin only for create/suspend |
| `TenantDomainPolicy` | `TenantDomain` — tenant admin |
| `TenantUserPolicy` | `TenantUser` — tenant admin / manager |
| `RolePolicy` | `Role` — tenant admin |
| `PlanPolicy` | `Plan` — platform admin write; tenant read |
| `SubscriptionPolicy` | `Subscription` — platform admin write; tenant billing read |
| `AuditLogPolicy` | `AuditLog` — platform/support vs tenant admin read scope |

### 6. Proposed routes

**Platform (super-admin) — prefix `/platform`, middleware `auth`, `EnsurePlatformAdmin`:**

| Method | URI | Controller action |
|--------|-----|-------------------|
| GET | `/platform` | Dashboard |
| Resource | `/platform/tenants` | Tenant CRUD + suspend/reactivate |
| Resource | `/platform/plans` | Plan management |
| GET/POST | `/platform/tenants/{tenant}/subscription` | Subscription management |
| GET | `/platform/audit-logs` | Global audit viewer |

**Tenant admin — prefix `/app/{tenant:uuid}`, middleware `auth`, `ResolveTenant`, `EnsureTenantActive`:**

| Method | URI | Controller action |
|--------|-----|-------------------|
| GET | `/app/{tenant}/dashboard` | Tenant dashboard |
| Resource | `/app/{tenant}/domains` | Domain management |
| Resource | `/app/{tenant}/users` | User invitation and roles |
| Resource | `/app/{tenant}/roles` | Role management (if custom roles) |
| GET | `/app/{tenant}/subscription` | View plan and usage |
| GET | `/app/{tenant}/audit-logs` | Tenant-scoped audit |

**Auth routes:** Laravel Breeze or Fortify (Livewire stack) — login, logout, password reset.

### 7. Proposed controllers

| Controller | Namespace |
|------------|-----------|
| `Platform\TenantController` | `App\Http\Controllers\Platform` |
| `Platform\PlanController` | |
| `Platform\SubscriptionController` | |
| `Platform\AuditLogController` | |
| `Platform\DashboardController` | |
| `Tenant\DashboardController` | `App\Http\Controllers\Tenant` |
| `Tenant\DomainController` | |
| `Tenant\UserController` | |
| `Tenant\RoleController` | |
| `Tenant\SubscriptionController` | |
| `Tenant\AuditLogController` | |

### 8. Proposed Livewire components

Install `livewire/livewire` v2 (compatible with Laravel 9) during Module 1.

| Component | Purpose |
|-----------|---------|
| `Platform\TenantIndex` | Searchable tenant list with status filters |
| `Platform\TenantForm` | Create/edit tenant |
| `Platform\SuspendTenantModal` | Suspension with reason |
| `Platform\PlanForm` | Plan and limit configuration |
| `Tenant\DomainManager` | Domain list and verification status |
| `Tenant\UserInviteForm` | Invite user with role |
| `Tenant\RolePermissionMatrix` | Assign permissions to roles |
| `Tenant\SubscriptionOverview` | Current plan, status, renewal |
| `Shared\AuditLogTable` | Paginated audit log viewer |

### 9. Proposed permissions

**Platform permissions:**

- `platform.tenants.view`, `platform.tenants.create`, `platform.tenants.update`, `platform.tenants.suspend`
- `platform.plans.manage`
- `platform.subscriptions.manage`
- `platform.audit.view`
- `platform.support.impersonate` (restricted)

**Tenant permissions:**

- `tenant.settings.manage`
- `tenant.domains.manage`
- `tenant.users.manage`
- `tenant.roles.manage`
- `tenant.subscription.view`
- `tenant.audit.view`
- `tenant.billing.view`

Map default roles: `tenant_owner`, `tenant_admin`, `manager`, `counsellor`, `content_manager`, `billing_user` per architecture §5.

### 10. Proposed automated tests

| Test suite | Coverage |
|------------|----------|
| `TenantIsolationTest` | User A cannot access Tenant B records by ID |
| `PlatformAdminAccessTest` | Only platform users reach `/platform/*` |
| `TenantSuspensionTest` | Suspended tenant blocked from `/app/*` |
| `SubscriptionStateTest` | Grace and expiry transitions |
| `EntitlementServiceTest` | Feature limits resolved correctly |
| `AuditLogTest` | Sensitive actions create audit records |
| `TenantProvisionerTest` | Tenant creation sets defaults |
| `DomainPolicyTest` | Domain CRUD authorisation |
| `PlanMoneyTest` | Prices stored as minor units |

Use `RefreshDatabase` trait; factories for `Tenant`, `User`, `Plan`, `Subscription`.

### 11. Tenant isolation approach

1. **Resolution:** Authenticated users resolve tenant via `tenant_user` membership; route model binding uses tenant `uuid`, not numeric `id`.
2. **Global scope:** `TenantOwnedScope` on all tenant models applying `where tenant_id = {resolved}`.
3. **Policies:** Every show/update/delete checks `$model->tenant_id === auth tenant`.
4. **Middleware:** `EnsureTenantActive` runs after resolution.
5. **Cache keys:** Prefix `tenant:{id}:` for all tenant-scoped cache.
6. **Tests:** Dedicated cross-tenant access attempt tests for each resource.

### 12. Super-admin separation

- `users.is_platform_user` boolean distinguishes platform staff from tenant users.
- Platform routes under `/platform` with separate layout and navigation.
- Platform users may have no `tenant_user` rows, or explicit support assignment per tenant.
- Tenant users never receive `platform.*` permissions.
- Support impersonation (if implemented) requires audit log entry before session switch.

### 13. Subscription and suspension enforcement

| State | Behaviour |
|-------|-----------|
| `trial` / `active` | Full access per entitlements |
| `grace` | Read-only or limited access; configurable warning |
| `past_due` | Block new sessions; preserve data |
| `suspended` | Block tenant app access; widget blocked in Module 2 |
| `cancelled` / `expired` | Block access; data retained per retention policy |

`SubscriptionService` + scheduled command `subscriptions:check-expiry` evaluates transitions daily. `TenantSuspensionService` for manual platform suspension. All transitions audited.

### 14. Audit logging approach

- `AuditLogger::record(action, subject, metadata)` called from services (not controllers directly).
- Immutable `audit_logs` table; no updates or deletes.
- Capture: actor, tenant (nullable for platform actions), IP, user agent, before/after snapshots in JSON metadata.
- Audited actions: tenant create/suspend/reactivate, subscription change, role change, domain add/verify, user invite/remove.

### 15. Risk areas

| Risk | Mitigation |
|------|------------|
| Cross-tenant data leak | Global scopes + policy tests on every model |
| PHP 8.0 / Laravel 9 EOL | Plan upgrade before production |
| Livewire + Laravel 9 version pairing | Pin compatible Livewire 2.x |
| Permission complexity | Start with seeded default roles; custom roles optional |
| Suspension bypass via API | Apply middleware consistently on all tenant routes |
| Premature feature scope | Strict Module 1 boundary — no widget or AI |
| Composer security advisories | `composer audit`; upgrade path documented |

### 16. Acceptance criteria

- [ ] Platform super-admin can create, view, update, suspend, and reactivate tenants
- [ ] Tenant admin can manage domains, users, and roles within their tenant only
- [ ] Plans and subscriptions can be assigned; status transitions work
- [ ] Suspended or expired tenants cannot access tenant dashboard
- [ ] Feature entitlements stored and retrievable per tenant
- [ ] All sensitive actions write to `audit_logs`
- [ ] Tenant isolation tests pass (minimum 100% on tenant-scoped resources)
- [ ] No widget, AI, payment, or messaging code introduced
- [ ] Documentation updated in this file and [MODULE_ROADMAP.md](MODULE_ROADMAP.md)
- [ ] Completion report filed per [CHANGE_REPORT_TEMPLATE.md](../agents/CHANGE_REPORT_TEMPLATE.md)

---

## Next recommended phase

**Module 1 — Multi-Tenant SaaS Foundation**

Begin with: install Livewire 2, create `tenants` and `users` migrations, platform authentication, and `TenantIsolationTest` scaffold.
