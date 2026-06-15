# ADR-006: Subscription and usage enforcement

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 9 — Subscription and usage enforcement

## Payment integration boundary

**Deferred.** Module 9 implements entitlement enforcement and manual platform administration only. No Razorpay, Stripe, or checkout flows. Provider reference fields on `subscriptions` are nullable for future integration.

## Subscription lifecycle

Governed by `SubscriptionLifecycleService`. States: `trialing`, `active`, `grace`, `past_due`, `expired`, `cancelled`. Controllers and UI cannot set status directly. Transitions recorded in `subscription_events` and security `audit_logs`.

## Plan-feature registry

`PlanFeature` enum is the authoritative feature catalogue. Plan entitlements stored in `plan_features` via `PlanEntitlement` model. Application code queries `EntitlementResolver` — never plan-name conditionals.

## Entitlement-resolution order

1. Tenant administrative status (suspension)
2. Subscription effective status (runtime date evaluation)
3. Plan entitlements
4. Tenant-specific overrides (`tenant_entitlement_overrides`)
5. Usage limits for the current period

## Plan versioning

Subscriptions reference live `plan_id` and `plan_features`. Platform admins must treat plan edits as affecting current subscribers unless overrides are applied. Historical integrity preserved via `subscription_events` and audit logs.

## Trial / grace / expiry

- Runtime checks use `Subscription::effectiveStatus()` — not solely scheduled jobs
- `subscriptions:maintain` command applies batch transitions idempotently
- Tenant Admin retains access to `/app/{tenant}/subscription` when expired

## Tenant suspension vs commercial restriction

Distinct states. Suspension is administrative (`tenants.status`). Subscription expiry is commercial (`subscriptions.status`). Both deny operational access; suspension also blocks widget. Documented separately in audit/events.

## Usage counting / reservation

- AI runs: count successful `ai_runs` in billing period
- Safe system fallbacks not counted as successful AI responses
- `tenant_usage_counters` with `reserve` / `confirm` / `release` around provider calls
- Failed provider calls release reservation without incrementing used count

## Widget behaviour under restriction

- Suspended / unavailable: safe generic message, no billing details
- Expired: widget unavailable; lead-capture-only if lead feature remains enabled
- Active human conversations: polling continues after expiry (continuity policy); new handoffs denied

## Existing human-conversation continuity

`human_conversation_continuity_on_expiry` config (default true): active human sessions may complete; new handoffs blocked when subscription expired.

## Scheduled maintenance role

`subscriptions:maintain` scheduled daily. Runtime entitlement checks remain authoritative between runs.

## Entitlement cache

Request-scoped cache in `EntitlementResolver`. Cleared on subscription transitions and tenant suspension.

## Suggested replies

Deferred (Module 8 boundary). `ai_runs.purpose` reserved.
