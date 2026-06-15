# ADR-003: Super Admin control plane and support access

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 6 — Super Admin Operations and Tenant Control Plane

## Decision

### Platform-scoped querying

Super Admin screens use dedicated services under `App\Services\Platform\*` that query across tenants intentionally. These services:

- Require `EnsurePlatformAdmin` middleware and explicit policies (`AiRunPolicy`, `AuditLogPolicy`, `TenantPolicy`).
- Never set `TenantContext` for aggregate reads.
- Return only safe, redacted operational fields (no decrypted API keys, prompts, or provider error bodies).

Tenant-scoped routes continue to use `TenantContext` and global scopes unchanged.

### Tenant suspension semantics

Suspension is a reversible operational state:

- Sets `status = suspended`, `suspended_at`, `suspension_reason`, and `suspended_by`.
- Requires a reason and creates an audit record.
- Blocks tenant access via existing `Tenant::allowsTenantAccess()` checks.
- Does not delete conversations, messages, AI runs, or usage records.

Reactivation clears suspension metadata and records `tenant.reactivated`.

Hard tenant deletion is **not** implemented in Module 6.

### Support impersonation

**Not implemented.** The architecture does not authorise unrestricted “login as tenant” behaviour for Module 6.

Future support access, if required, must be a separate explicit feature with:

- Audited start/end, reason, visible banner, short TTL, and no secret exposure.

### Platform settings and secret ownership

Platform-wide settings are stored in `platform_settings` (key/value JSON). Platform OpenAI credentials:

- Are submitted via password-style inputs only.
- Are encrypted at rest (`Crypt::encryptString`).
- Are never returned to the browser or included in audit metadata in cleartext.
- Replacement is audited via `ai.secret_replaced` (scope: platform) and `platform.settings_updated`.

Tenant credentials remain tenant-owned per ADR-002.

### Usage and cost reporting

Module 6 reports **tokens and run counts only**. No currency cost is calculated because no versioned pricing configuration exists. Token figures are never labelled as monetary amounts.

## Consequences

- Super Admins operate through a dedicated platform layout and navigation distinct from tenant dashboards.
- Counsellor/staff roles cannot access platform routes.
- Lead qualification (previously listed as Module 6 in the master roadmap sequence) is deferred to a later module; this delivery replaces that slot with the control plane required to operate the SaaS safely.
