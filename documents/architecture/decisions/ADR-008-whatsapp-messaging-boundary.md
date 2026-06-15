# ADR-008: WhatsApp messaging boundary

**Status:** Accepted  
**Date:** 2026-06-15  
**Module:** 11 — WhatsApp

## Context

Module 11 delivers tenant-owned WhatsApp Business Cloud API integration with inbound webhooks, outbound replies, delivery status, templates, and channel-aware conversations — without coupling provider logic to leads, AI, or handoff services.

The roadmap title is **WhatsApp — Outbound messaging integration**; implementation scope includes full Cloud API integration per architecture and product requirements.

## Decision

### Provider

Use **Meta WhatsApp Cloud API** via `MetaWhatsAppCloudProvider`. Tests use `FakeMessagingProvider` at `/webhooks/messaging/fake` (testing environment only).

No unofficial automation, scraping, or personal-account bots.

### Provider-neutral interfaces

- `MessagingProviderContract`
- `InboundMessageProcessor`
- `OutboundMessageService`
- `MessagingWebhookService`
- `MessagingCredentialService`
- `TemplateMessageService`

Meta-specific HTTP and signature logic lives only in provider adapters.

### Webhook tenant resolution

Resolve tenant from `metadata.phone_number_id` on the integration row. Never trust client-supplied tenant IDs in webhook payloads.

### Signature verification

`X-Hub-Signature-256` HMAC-SHA256 over the raw request body using the tenant's encrypted app secret.

### Idempotency

- `messaging_webhook_events (provider, provider_event_id)` for webhook deduplication
- `messages (tenant_id, provider_message_id)` for inbound message deduplication

### Channel-aware conversations

Extend existing `conversations` and `messages` with WhatsApp fields. Reuse `ConversationService`, `AiConversationOrchestrator`, `LeadCreationService`, `ConversationHandoffService`, and `EntitlementResolver`.

### Session window / template policy

24-hour customer-care window per Meta policy (`config('messaging.service_window_hours')`, default 24). Free-form outbound only inside the window; outside requires an approved template via `TemplateMessageService`.

### Subscription expiry continuity

Store inbound messages; block new outbound/AI when `whatsapp_integration` or `ai_responses` entitlements deny. Suspended tenants receive safe webhook acknowledgement without operational processing.

### Queue vs synchronous processing

Webhook verification and event persistence are synchronous. Inbound/outbound processing runs synchronously in the HTTP request (no queue worker required for local/cPanel). Documented honestly in `LOCAL_SETUP.md`.

### Credential ownership

Each tenant stores its own encrypted access token and app secret. Secrets are never returned to the browser. Blank secret fields preserve existing values. Replacement is audited.

### Operational events vs audit

`messaging_events` records operational history (webhook accepted, outbound submitted, delivery updated). Security audit records configuration changes (`MessagingIntegrationConfigured`, credential replacement, enable/disable).

## Consequences

- Widget and payment modules remain unchanged.
- Platform Super Admin sees integration health only (masked phone, no tokens, no message bodies).
- Production Meta app configuration and review remain tenant/platform operational tasks outside this codebase.

## Local testing

- `FAKE_MESSAGING_ENABLED=true` in `phpunit.xml`
- `Http::fake()` for provider HTTP
- Webhook route: `POST /webhooks/messaging/fake` with HMAC signature using `config('messaging.providers.fake.app_secret')`

No production credentials or external API calls are used in automated tests.
