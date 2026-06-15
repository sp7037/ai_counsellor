# ADR-002 — AI credential modes and mandatory idempotency

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 5 corrective completion pass

## Decision

### Credential ownership modes

`tenant_ai_configs.credential_mode` is explicit and must be configured by authorized owner/admin users:

| Mode | Behaviour |
|------|-----------|
| `tenant_key_required` | Tenant-owned encrypted key is mandatory. Missing key fails safely with `missing_key`. |
| `platform_managed` | Platform environment key is used. Tenant key is ignored even if present. |
| `tenant_key_with_explicit_platform_fallback` | Tenant key is preferred. Platform key is used only when tenant key is absent and this mode is deliberately selected. |

Rules:

- No silent fallback when `tenant_key_required` is selected.
- `platform_managed` never exposes the platform key in UI/API.
- `ai_runs.credential_source` records only `tenant` or `platform`, never secret material.
- Credential mode changes are audited via `ai.configuration_updated`.

### Mandatory server-side idempotency

Widget `request_id` is optional client metadata only. The server always derives a canonical idempotency key:

1. Client UUID when valid.
2. Otherwise the immutable visitor message UUID after persistence.

Database protections:

- `unique(tenant_id, request_uuid)` on `ai_runs`
- `unique(tenant_id, conversation_id, request_uuid)` on visitor `messages.request_uuid`
- `ai_runs.triggering_message_id` links each attempt to the immutable visitor message
- Short DB transactions claim/replay runs; provider calls occur outside transactions
- Failed runs may be retried on the same request UUID without creating duplicate visitor messages
- Only one successful assistant reply may be finalized per triggering visitor message

### Provider failure semantics

- Failed provider calls persist `ai_runs.status = failed` with safe `error_category`
- No assistant message is linked to failed runs
- Visitor-safe fallback is returned as a `system` role message and excluded from later provider history
- Fallback text is operational only and must not fabricate counselling answers

## Consequences

- Cross-tenant request UUID collisions are isolated by tenant-scoped uniqueness.
- Platform operators can run centrally managed AI for tenants without storing tenant keys.
- Tenants requiring BYOK must select `tenant_key_required` or explicit fallback consciously.
- Retry and duplicate-widget-submit scenarios are safe without duplicate billing-grade assistant output.

## Deferred

- Module 6 lead qualification / OTP flows
- Billing enforcement on `ai_runs` usage
- Multi-provider routing beyond OpenAI + fake adapters
