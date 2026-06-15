# Module 10 — Payments schema

**Last updated:** 2026-06-15  
**Status:** Implemented

## Plan pricing (additive on `plans`)

| Column | Purpose |
|--------|---------|
| `currency` | ISO 4217 (nullable until configured) |
| `amount_minor` | Integer smallest currency unit |
| `billing_interval_count` | Interval multiplier (default 1) |
| `tax_treatment` | `inclusive` / `exclusive`, nullable |
| `setup_fee_minor` | Optional one-time fee in minor units |
| `provider_price_id` | Optional provider catalogue reference |
| `is_purchasable` | Self-service checkout flag (default false) |
| `pricing_effective_from` | When current commercial price became effective |

Plans remain non-purchasable until a Super Admin sets price and currency.

## `payment_orders`

Internal checkout orders (authoritative amount/currency).

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (tenant_id, checkout_request_uuid)` | Checkout idempotency |
| `UNIQUE (provider, provider_mode, provider_order_id)` | Provider order deduplication |
| `UNIQUE internal_reference` | Receipt reference |

Statuses: `pending`, `created`, `paid`, `failed`, `cancelled`, `expired`.

## `payments`

Verified provider payments. Amount immutable after capture.

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (provider, provider_mode, provider_payment_id)` | Idempotent payment records |

Statuses: `created`, `authorized`, `captured`, `failed`, `refunded`, `partially_refunded`.

Activation requires `captured` for Razorpay automatic-capture flow.

## `payment_events`

Append-only operational payment history (order created, verified, captured, activation requested/completed, webhook received, etc.).

## `payment_webhook_events`

Deduplicated webhook ingestion.

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (provider, provider_mode, provider_event_id)` | Duplicate webhook ignore |

## Activation boundary

Verified payment → `BillingService::finalizeVerifiedPayment` → `SubscriptionLifecycleService::applyVerifiedPayment`. Controllers and webhooks never set subscription status directly.

## Reconciliation

`payments:reconcile` expires abandoned orders (hourly schedule). Provider polling reconciliation deferred.

## Refunds

Schema supports `refunded_amount_minor` on `payments`. Provider refund flows deferred (ADR-007).

See [ADR-007](decisions/ADR-007-payments-and-billing-boundary.md).
