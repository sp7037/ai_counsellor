# Module 5 — AI Orchestration Schema

**Last updated:** 2026-06-15 (Module 5 — complete)

## Module scope

**Title:** Module 5 — AI orchestration

**Objective:** Integrate provider-neutral AI reply orchestration into widget conversations using tenant-safe published knowledge retrieval, controlled prompt construction, safe failure handling, and usage logging.

**Exit gate:** Model cannot bypass tool/service validation.

## Implemented providers

- OpenAI adapter (`OpenAiProvider`)
- Fake provider for deterministic tests (`FakeAiProvider`)

Future providers (deferred): Gemini, Claude, DeepSeek.

## Provider abstraction

- Contract: `App\Contracts\AI\AiProviderContract`
- DTOs: `AiRequest`, `AiResponse`, `AiMessage`, `AiUsage`
- Registry: `AiProviderRegistry`
- Provider-specific exceptions:
  - `AiProviderException`
  - `AiTimeoutException`
  - `AiRateLimitException`
  - `AiAuthenticationException`
  - `AiContentPolicyException`

No provider SDK response type leaks beyond adapter boundary.

## Tables and relationships

### `ai_providers` (global)

| Column | Notes |
|--------|-------|
| `slug` | controlled provider identifier (`openai`) |
| `name` | display name |
| `supports_tools` | capability flag |
| `enabled` | provider availability |

### `tenant_ai_configs` (tenant-owned)

| Column | Notes |
|--------|-------|
| `uuid` | public identifier |
| `tenant_id` | FK → `tenants` |
| `provider_id` | FK → `ai_providers` |
| `model` | model identifier |
| `temperature` | bounded numeric |
| `max_output_tokens` | bounded int |
| `timeout_seconds` | bounded int |
| `enabled` | enable/disable tenant AI |
| `credential_mode` | `tenant_key_required`, `platform_managed`, `tenant_key_with_explicit_platform_fallback` |
| `encrypted_api_key` | encrypted at rest (nullable) |
| `secret_updated_at` | rotation timestamp |
| `created_by`, `updated_by` | actor tracking |

Unique: one config per tenant.

### `ai_runs` (tenant-owned operational usage records)

| Column | Notes |
|--------|-------|
| `request_uuid` | tenant-scoped idempotency key (mandatory server-side; client `request_id` optional) |
| `triggering_message_id` | FK → visitor `messages.id` |
| `credential_source` | `tenant` or `platform` (never secret values) |
| `attempt_number` | retry counter for failed-run recovery |
| `tenant_id` | FK → `tenants` |
| `conversation_id` | FK → `conversations` |
| `message_id` | assistant message FK (nullable until success persisted) |
| `provider`, `model` | execution selection |
| `input_tokens`, `output_tokens`, `total_tokens` | usage metrics |
| `latency_ms` | response latency |
| `status` | `processing`, `success`, `failed` |
| `error_category` | normalized safe failure category |

## Ownership and secrecy

- AI provider definitions are platform-owned (`ai_providers`)
- Tenant runtime configuration is tenant-owned (`tenant_ai_configs`)
- Secrets are encrypted at rest; raw values are never returned in UI/API responses
- Environment fallback remains supported only through explicit `credential_mode` selection (see ADR-002)

## Orchestration flow

1. Widget message accepted through existing key/session/origin validation
2. Visitor message persisted (immutable)
3. Published knowledge retrieved via `KnowledgeRetrievalContract`
4. Prompt built by `AiPromptBuilder` with:
   - platform system policy
   - tenant assistant policy section
   - bounded knowledge references
   - bounded conversation history
5. `AiConversationOrchestrator` resolves effective config and provider adapter
6. Provider called with strict timeout limits
7. Success:
   - assistant message persisted
   - `ai_runs` updated with usage/latency
8. Failure:
   - no fake assistant success persisted
   - safe system fallback message persisted
   - `ai_runs` failed with safe error category

## Prompt safety controls

- Untrusted separation: visitor text and knowledge excerpts treated as reference content only
- Instructions explicitly forbid revealing secrets/system policies/hidden metadata
- Context limits enforced for history length, excerpt size, and input/output size
- Plain text output only; no HTML/script execution path

## Widget/API integration

- Existing endpoint preserved: `POST /widget/v1/messages`
- Added optional `request_id` UUID; server always enforces idempotency even when omitted
- Response includes persisted `visitor_message` + `reply`
- Reply role:
  - `assistant` on successful AI generation
  - `system` for safe fallback on provider/runtime failure

## Authorization and admin UI

- Route: `/app/{tenant}/ai/configuration`
- Livewire page: `tenant.ai.configuration`
- Access:
  - platform super-admin: allowed
  - tenant owner/admin: allowed
  - tenant staff: denied
- Server-side validation and policy checks on every mutation

## Audit events

- `ai.configuration_updated`
- `ai.secret_replaced`

Audit metadata excludes raw secrets.

## Tests added

`tests/Feature/AiOrchestrationTest.php` plus corrective suites:

- `AiIdempotencyTest.php`
- `AiCrossTenantIsolationTest.php`
- `AiPromptSafetyTest.php`
- `AiSecretLeakageTest.php`
- `AiProviderFailureTest.php`
- `OpenAiProviderHttpTest.php`
- `AiUsageIntegrityTest.php`

Coverage includes mandatory idempotency, cross-tenant orchestration isolation, prompt trust hierarchy, secret redaction, credential modes, provider HTTP safety, failed-run semantics, and usage integrity.

Regression suites for Modules 1–4 remain passing.

## Exclusions (Module 5 boundary)

- Module 6 lead qualification and OTP workflows
- Module 7 human agent workspace/realtime takeover
- Module 8 billing/quota enforcement
- Multi-provider routing policy beyond OpenAI-first + fake test adapter
- Tool execution framework beyond guarded retrieval orchestration
