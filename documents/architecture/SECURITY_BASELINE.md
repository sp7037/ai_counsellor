# Security Baseline

**Last updated:** 2026-06-15 (Module 5)

## Principal reference

Full security, privacy, and compliance controls are defined in [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §14. This document is the enforced baseline for all modules.

## Module 1 enforcement (implemented)

- Tenant context resolved server-side via `ResolveTenant` middleware; never from request `tenant_id` alone
- `ClearTenantContext` + `ResolveTenant::terminate()` clear context after every web request
- `BelongsToTenant` fail-closed global scope when isolation enforced; `tenant_id` not mass-assignable or updatable
- Platform `/platform/*` routes require `platform.admin` middleware and never inherit tenant context
- Login rate limiting: 5 attempts/minute per email+IP
- Public registration disabled; Fortify registration feature off
- Password reset Livewire flow uses generic message (does not reveal account existence)
- Membership lifecycle audited via `MembershipLifecycleService` (create, role change, status change, remove)
- Final active tenant owner cannot be removed/deactivated/demoted without another owner
- Disabled user accounts (`users.status = disabled`) are logged out on next request
- Email verification required for protected dashboards (`verified` middleware)
- Fortify 2FA/passkey migrations retained; features disabled in config (no routes)

## Module 2 enforcement (implemented)

- Public widget resolves tenant via **widget public key + verified Origin/Referer** only; never client `tenant_id`
- Widget session tokens stored as SHA-256 hashes; plain token returned once at session start
- `HandleWidgetCors` reflects request `Origin`; gateway rate-limited per `config/widget.php`
- `ResolveWidgetSession` validates token, tenant active state, and origin binding
- Widget gateway sets `TenantContext::setFromWidgetGateway()` with isolation enforced; cleared via `ClearTenantContext` on `api` group
- Widget key rotation/revocation and domain verify/remove audited
- Tenant admin widget mutations authorized in policies; cross-tenant UUID/ID lookups return 404 under tenant scope
- Suspended/cancelled/pending tenants rejected at widget session start
- `WIDGET_ALLOW_LOCAL_ORIGINS` unset defaults to allow localhost **only** in `local`/`testing`; production must not rely on localhost bypass

## Module 3 enforcement (implemented)

- Tenant configuration mutations centralized in dedicated services with validation and audit
- Logo uploads: JPEG/PNG/WebP only, server-side MIME verification, 2 MB limit, random filenames under `tenant-logos/{tenant_uuid}/`
- All tenant-facing text sanitized with `strip_tags` before storage
- Widget public `configuration` payload excludes internal IDs, secrets and admin metadata
- Catalogue CRUD tenant-scoped; staff cannot mutate configuration
- Office hours evaluated in tenant IANA timezone, not server default alone

## Module 4 enforcement (implemented)

- Knowledge mutations centralized in dedicated services with transactional publish/archive and audit
- Draft and archived knowledge never exposed via widget search or public APIs
- Published content served only through `KnowledgeRetrievalContract` / `PublishedKnowledgeSearchService`
- Immutable `knowledge_versions` rows; monotonic `version_number` with unique constraint per item
- Plain-text sanitization via `KnowledgeContentSanitizer` (scripts, event handlers, `javascript:` URLs stripped)
- Source documents stored on private `local` disk under `knowledge-documents/{tenant_uuid}/`; authorized download only
- Document uploads: PDF/DOC/DOCX/TXT only, server-side MIME verification, size limit, random filenames
- Fees stored as integer minor units (no floats); eligibility criteria as bounded plain text (no executable expressions)
- Catalogue links (`service_id`, `course_id`, etc.) validated tenant-scoped; cross-tenant attachment returns 404
- Staff may view knowledge admin pages but cannot create, publish, archive, upload or delete
- Widget `GET /widget/v1/knowledge/search` requires valid session + origin; production localhost rejected when `WIDGET_ALLOW_LOCAL_ORIGINS` is false or absent

## Module 5 enforcement (implemented)

- AI provider access isolated behind `AiProviderContract`; provider-specific payloads stay inside adapters
- Widget AI flow resolves tenant from existing widget key/session/origin pipeline only
- Prompt construction separates trusted policy from untrusted user/knowledge text and bounds history/context sizes
- Knowledge context for AI uses `KnowledgeRetrievalContract` (published-only, tenant-scoped, no drafts/private files)
- Tenant AI secrets are encrypted at rest in `tenant_ai_configs`; raw keys are never returned after save
- AI failures (timeout/auth/rate-limit/provider) return safe fallback without exposing provider internals
- Assistant output persisted as plain text and rendered as text in widget
- Idempotency key (`request_id`) maps to tenant-scoped `ai_runs.request_uuid`; server generates key from visitor message UUID when omitted
- Explicit `credential_mode` controls tenant/platform credential ownership (ADR-002)
- Published knowledge retrieval must filter by `tenant_id` explicitly
- AI config changes and secret replacement are audited with redacted metadata

## Secrets and credentials

| Rule | Requirement |
|------|-------------|
| No API keys in frontend JavaScript | Widget and public assets must use public keys only |
| No plaintext passwords | Use Laravel hashing (`bcrypt` / `argon2`) |
| No secrets in Git | `.env` must remain in `.gitignore` |
| Encrypt provider keys at rest | AI, messaging, payment, webhook credentials |
| No logging of secrets | Do not log OTP values, raw API keys, or full payment card data |

## Authentication and authorisation

- **CSRF protection** must remain enabled for authenticated browser actions.
- **Validate** all incoming data using Form Requests or equivalent.
- **Authorise** all protected actions using policies, gates, or middleware.
- **Rate-limit** authentication endpoints, OTP, password reset, and public widget APIs.
- Use **signed tokens** for public continuation links (inquiry, payment) with expiry.

## Tenant security

- **Do not trust `tenant_id` submitted by the browser** as authority.
- Tenant resolution order (architecture §7):
  1. Authenticated tenant membership
  2. Widget public key + verified Origin/Referer
  3. Scoped API key
  4. Signed platform support context
- **Domain whitelist** must be validated server-side for widget sessions.
- Authorisation policies must verify tenant ownership even when a record ID is known.
- Cross-tenant analytics available only to platform roles.

## AI security

- AI providers must **never** receive unrestricted database credentials.
- The AI must only request **approved application tools** through the service layer.
- AI cannot execute raw SQL or unrestricted application actions.
- Model calls require timeout, retry policy, idempotency, and usage logging.
- Fallback routing must never silently use a provider prohibited by the tenant.

## Application hardening

| Control | Requirement |
|---------|-------------|
| Mass assignment | Use `$fillable` / `$guarded`; never mass-assign sensitive fields |
| Output escaping | Escape untrusted output in Blade; prevent XSS |
| SQL injection | Use Eloquent / query builder parameter binding |
| File uploads | Malware scanning and content restrictions (when implemented) |
| Cookies (production) | Secure, HTTP-only, SameSite |
| HTTPS | Required in production |
| CORS | Strict policy for widget gateway API |

## Suspension and access control

- Platform must be able to **suspend tenants** immediately.
- Suspended tenants: reject new AI sessions; preserve data; show configurable message.
- Provide **account and tenant suspension** controls with audit trail.

## Privacy and compliance

- Store **consent** text version, purpose, timestamp, and source.
- Provide retention periods and data subject request workflows (access, correction, deletion, export).
- Keep personal and health information collection **minimal**.
- Payment card details remain with the payment gateway; store only gateway references.

## Healthcare boundary

Healthcare functionality is initially limited to:

- Administrative intake
- Information provision
- Scheduling
- Authorised human transfer

The general AI assistant must **not** diagnose or prescribe. Escalate potential medical diagnosis, prescription, or urgent health concerns to humans.

## Backups and disaster recovery

- Backups must be encrypted and tested.
- Document tenant data restoration procedures.
- Define RPO/RTO targets for production (see [VPS_PRODUCTION_REQUIREMENTS.md](../setup/VPS_PRODUCTION_REQUIREMENTS.md)).

## Audit logging

Log security-sensitive actions including:

- Tenant activation and suspension
- Subscription changes
- Role changes
- AI configuration changes
- Knowledge publication
- Lead reassignment
- Payment state changes
- Human takeover
- Data export and deletion

## Development agent obligations

Agents must never:

- Deploy without explicit instruction
- Modify production data
- Expose or hard-code API keys
- Bypass tenant isolation
- Disable CSRF or security middleware without documented ADR approval
