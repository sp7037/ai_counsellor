# Module 11 — WhatsApp schema

**Last updated:** 2026-06-15  
**Status:** Implemented

## `tenant_messaging_integrations`

One integration row per tenant (unique `tenant_id`).

| Column | Purpose |
|--------|---------|
| `provider` | `meta` or `fake` (tests only) |
| `phone_number_id` | Authoritative webhook tenant resolution key |
| `waba_id` | WhatsApp Business Account ID |
| `display_phone_number` | Human-readable display |
| `verify_token` | Meta webhook verification |
| `access_token` | Encrypted JSON (`encrypted` key) |
| `app_secret` | Encrypted JSON for webhook HMAC |
| `is_enabled` | Operational toggle |
| `status` | `disabled`, `pending`, `connected` |
| `last_webhook_at` | Last successful webhook processing |
| `last_outbound_success_at` | Last provider send success |
| `last_error_category` | Safe failure category |

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (provider, phone_number_id)` | Global phone-number isolation |

## `messaging_contacts`

Per-integration channel contacts.

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (messaging_integration_id, external_contact_id)` | Normalized phone identity |

`last_inbound_at` drives the 24-hour service window (Meta customer-care window policy).

## `messaging_templates`

Approved template metadata per integration (manual sync in Module 11).

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (messaging_integration_id, provider_template_name, language_code)` | Template identity |

## `messaging_webhook_events`

Deduplicated provider webhook ingestion.

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (provider, provider_event_id)` | Duplicate webhook ignore |

Payload metadata is redacted (no message bodies).

## `messaging_events`

Append-only operational integration history (distinct from security audit log).

## Conversation extensions

| Column | Purpose |
|--------|---------|
| `messaging_integration_id` | Provider integration link |
| `messaging_contact_id` | WhatsApp contact |
| `external_channel_reference` | Normalized external contact id |
| `last_inbound_provider_message_id` | Reply context |

## Message extensions

| Column | Purpose |
|--------|---------|
| `direction` | `inbound` / `outbound` |
| `provider_message_id` | Provider message idempotency |
| `delivery_state` | `submitted`, `sent`, `delivered`, `read`, `failed` |
| `template_name` | Template sends only |
| `reply_to_provider_message_id` | Optional provider context |
| `delivery_failure_category` | Safe failure category |

| Constraint | Purpose |
|------------|---------|
| `UNIQUE (tenant_id, provider_message_id)` | Duplicate message ignore (NULL-safe) |

## Entitlement

`PlanFeature::WhatsAppIntegration` (`whatsapp_integration`) on Professional and Enterprise plans.

## Subscription expiry continuity

- Verified inbound messages are stored.
- No new AI/provider outbound after entitlement denial.
- Tenant Admin may view integration status and historic data.
- Suspended tenants: webhook acknowledged, processing skipped (`tenant_inactive`).

See [ADR-008](decisions/ADR-008-whatsapp-messaging-boundary.md).
