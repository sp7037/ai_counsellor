# Module 6 Schema — Super Admin Operations and Tenant Control Plane

**Last updated:** 2026-06-15  
**Status:** Complete

## Overview

Module 6 adds platform-level operational storage and services for Super Admin control-plane screens. Tenant isolation and Module 5 AI guarantees are unchanged.

## New migrations

### `2026_06_15_600001_add_suspended_by_to_tenants_table`

| Column | Type | Notes |
|--------|------|-------|
| `suspended_by` | `foreignId` → `users`, nullable | User who suspended the tenant |

Existing suspension fields (`status`, `suspended_at`, `suspension_reason`) were introduced in Module 1.

### `2026_06_15_600002_create_platform_settings_table`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `key` | string(120), unique | Setting identifier |
| `value` | json, nullable | Non-secret settings; encrypted credential blob for platform API key |
| `updated_by` | `foreignId` → `users`, nullable | Last editor |
| `created_at` / `updated_at` | timestamps | |

## Platform settings keys

| Key | Purpose |
|-----|---------|
| `default_provider` | Default AI provider identifier |
| `allowed_models` | Allowed model list (JSON array) |
| `default_fallback_message` | Global safe fallback copy |
| `support_email` | Operational support contact |
| `platform_openai_api_key` | `{ "encrypted": "..." }` — never exposed to UI |

## Services (`App\Services\Platform`)

| Service | Responsibility |
|---------|----------------|
| `PlatformDashboardService` | Overview cards, alerts, recent tenants, AI summary |
| `PlatformTenantDirectoryService` | Paginated tenant search/filter/detail |
| `PlatformAiOperationsService` | Paginated AI run monitoring, safe detail |
| `PlatformUsageReportingService` | Token/run aggregates by period, tenant, provider |
| `PlatformAuditLogService` | Paginated audit log viewer, metadata redaction |
| `PlatformSettingsService` | Read/write platform settings, encrypt platform key |
| `PlatformSystemHealthService` | Lightweight health checks |
| `TenantAiStatusPresenter` | Safe AI credential status labels (no secrets) |

## Authorization

| Policy / middleware | Scope |
|---------------------|-------|
| `EnsurePlatformAdmin` | All `/platform/*` routes |
| `TenantPolicy` | Tenant CRUD, suspend/reactivate |
| `AiRunPolicy` | Platform AI run inspection |
| `AuditLogPolicy` | Platform audit log read |

## Routes (prefix `/platform`)

| Route name | Path | Page |
|------------|------|------|
| `platform.overview` | `/platform` | Dashboard |
| `platform.tenants.*` | `/platform/tenants` | Tenant management |
| `platform.ai-operations.index` | `/platform/ai-operations` | AI operations |
| `platform.usage.index` | `/platform/usage` | Usage reporting |
| `platform.audit-logs.index` | `/platform/audit-logs` | Audit logs |
| `platform.settings.index` | `/platform/settings` | Platform settings |
| `platform.failed-runs.index` | `/platform/failed-runs` | Redirect/filter to failed runs |
| `platform.system-health.index` | `/platform/system-health` | System health |

## Audit actions added

| Action | When |
|--------|------|
| `platform.settings_updated` | Platform settings saved |

Existing actions reused: `tenant.suspended`, `tenant.reactivated`, `ai.secret_replaced`, `tenant.created`, etc.

## Related ADRs

- [ADR-001](decisions/ADR-001-authentication-foundation.md) — privileged email verification preserved
- [ADR-002](decisions/ADR-002-ai-credential-modes-and-idempotency.md) — credential mode terminology
- [ADR-003](decisions/ADR-003-super-admin-control-plane.md) — platform scoping, suspension, no impersonation

## Deferred

- Support impersonation / “login as tenant”
- Lead qualification workflows (see roadmap reorder note)
- Monetary cost reporting
