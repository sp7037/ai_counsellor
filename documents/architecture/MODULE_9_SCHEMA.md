# Module 9 — Subscription and usage enforcement schema

**Last updated:** 2026-06-15  
**Status:** Implemented

## Tables

### `plans`

Platform-defined packages. No price fields (payment deferred).

| Column | Purpose |
|--------|---------|
| `code` | Immutable-style identifier (`trial`, `starter`, …) |
| `billing_interval` | Metadata only (`monthly`, `annual`) |
| `status` | `active` / `inactive` |

### `plan_features`

| Column | Purpose |
|--------|---------|
| `feature` | `PlanFeature` enum value |
| `enabled` | Feature on/off |
| `limit_value` | Nullable = unlimited |
| `limit_period` | `billing_period`, `total`, `monthly` |

### `subscriptions`

One current subscription per tenant (`UNIQUE tenant_id`).

| Column | Purpose |
|--------|---------|
| `status` | `SubscriptionStatus` enum |
| `trial_ends_at`, `current_period_ends_at`, `grace_ends_at` | Runtime enforcement |
| `cancel_at_period_end` | Scheduled cancellation |
| `provider_*` | Nullable payment-provider readiness |

### `subscription_events`

Operational subscription history (not security audit).

### `tenant_entitlement_overrides`

Per-tenant feature/limit overrides with optional expiry.

### `tenant_usage_counters`

Per-period usage with `used_value` and `reserved_value` for AI concurrency.

### `subscription_usage_warnings`

Deduped usage threshold notifications.

## Enforcement layers

| Layer | Component |
|-------|-----------|
| Resolver | `EntitlementResolver` |
| Middleware | `EnsureTenantOperational`, `EnsureFeatureEntitled`, `EnsureCounsellorSubscription` |
| Services | AI, knowledge, leads, counsellors, handoff, widget |

## Seeding

```bat
D:\php83\php.exe artisan db:seed --class=PlansSeeder
```

## Maintenance

```bat
D:\php83\php.exe artisan subscriptions:maintain
```

Scheduled daily via `routes/console.php`.

See [ADR-006](decisions/ADR-006-subscription-and-usage-enforcement.md).
