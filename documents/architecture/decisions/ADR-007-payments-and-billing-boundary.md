# ADR-007: Payments and billing boundary

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 10 — Payments

## Payment provider

**Razorpay** is the first production provider (India-first market). **Fake** adapter is used in automated tests and local development. Provider-neutral `PaymentProviderContract` with `RazorpayPaymentProvider` and `FakePaymentProvider`.

Stripe and other providers are not claimed as implemented.

## Provider-neutral services

| Service | Responsibility |
|---------|----------------|
| `PaymentOrderService` | Internal order + checkout idempotency |
| `PaymentVerificationService` | Browser signature verification |
| `PaymentWebhookService` | Webhook signature + deduplication |
| `BillingService` | Race-safe finalization |
| `SubscriptionLifecycleService::applyVerifiedPayment` | Subscription activation/renewal only |

Entitlement logic remains in `EntitlementResolver`.

## Order and payment states

Orders: `pending` → `created` → `paid` | `failed` | `cancelled` | `expired`.  
Payments: activation requires `captured` (Razorpay auto-capture). `authorized` alone does not activate.

## Callback / webhook idempotency

- Unique `(tenant_id, checkout_request_uuid)` for checkout
- Unique `(provider, provider_mode, provider_payment_id)` for payments
- Unique `(provider, provider_mode, provider_event_id)` for webhooks
- `subscription_activation_completed_at` on order prevents double renewal

## Subscription activation policy

On verified capture:

| Situation | Policy |
|-----------|--------|
| No subscription | Create active subscription, `source=payment` |
| Trialing | Immediate paid activation; trial ends |
| Active, same plan | Extend from current period end if still active; else from now |
| Active, different plan | Immediate plan change + new period from now; no proration/refund |
| Expired / grace / past due | Activate from now |

## Test / live mode

`PaymentEnvironment` stored on each order/payment. Platform settings + `config/payments.php`. Test mode cannot silently fall back to live credentials.

## Price storage

Integer `amount_minor` + `currency` on `plans`. Order amount frozen at creation (includes optional `setup_fee_minor`). Plan price edits do not alter open/paid orders.

## Receipt vs tax invoice

HTML **payment receipt** only. No GSTIN, tax lines, or invoice numbering until legal/accounting sign-off.

## Refunds

**Deferred.** `refunded_amount_minor` column reserved. No refund UI or provider refund API in Module 10.

## Reconciliation

`payments:reconcile` expires stale orders synchronously. Provider status polling deferred until production scheduler and credentials are configured.

## Financial record immutability

No hard-delete of payments/orders. Corrections via events and future reconciliation/refund flows. Captured amounts not rewritten by stale failure webhooks.

## Secrets

Razorpay key secret and webhook secret encrypted in `platform_settings`. Key ID may appear in checkout frontend. No secrets in logs, tests, or Git.
