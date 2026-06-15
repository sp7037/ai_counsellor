# ADR-004: Lead lifecycle, assignment, and counsellor workspace

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 7 — Lead Qualification and Human Agent Workspace

## Background

The master architecture originally listed lead qualification as Module 6. Module 6 was delivered as the Super Admin control plane (ADR-003). Lead qualification and human agent workspace are restored together as Module 7 without removing or rewriting the control plane.

## Decision

### Counsellor role boundaries

- Counsellors are existing tenant members with `TenantRole::Staff`.
- Profile extensions live in `counsellor_profiles` keyed to `tenant_user` membership — no separate authentication table.
- Staff access a dedicated workspace at `/app/{tenant}/workspace/*` with `EnsureCounsellorWorkspace` middleware.
- Staff are denied `/platform/*`, tenant lead admin routes, and leads assigned to other counsellors.
- Platform super admins are denied routine lead routes via `LeadPolicy` (no automatic participation in tenant lead workflows).

### Lead assignment model

- Current assignment denormalised on `leads.assigned_to` / `assigned_at` for efficient filtering.
- Immutable history in `lead_assignments` with `is_current` boolean; reassignment closes previous row.
- Cross-tenant assignment rejected in `LeadAssignmentService`.
- Inactive or non-Staff users cannot receive assignments.

### Lead lifecycle and transitions

- `LeadStage`, `LeadQualificationStatus`, and `LeadPriority` are governed enums — not free text.
- `LeadTransitionService` enforces allowed transitions for counsellors.
- Tenant Admin may override stages through authorised service paths with audit + activity records.
- Reopening closed leads requires explicit `reopen` action with audit.

### Deterministic vs AI-assisted qualification

- `LeadQualificationEngine` computes an advisory score (0–100) from explainable, tenant-configurable factors.
- Prohibited attributes (religion, caste, ethnicity, gender, etc.) are never scored.
- Score does not predict admission or legal outcomes.
- AI suggestion fields (`ai_suggested_summary`, `ai_suggested_score`, `ai_suggested_priority`) are reserved; provider calls for qualification are **deferred** — deterministic scoring runs at lead creation without blocking on AI.

### Lead/contact deduplication policy

- Widget capture idempotency: unique `(tenant_id, capture_event_uuid)`.
- Integration idempotency: unique `(tenant_id, source, source_reference)` when reference provided.
- **No** deduplication of different enquiries by mobile or email alone — separate genuine enquiries remain separate leads.

### Internal activity vs security audit

| Concern | Storage | Audience |
|---------|---------|----------|
| Operational lead timeline | `lead_activities` | Tenant Admin + assigned counsellor on lead detail |
| Security/compliance events | `audit_logs` via `AuditLogger` | Platform/tenant audit viewers |

Passwords, API keys, and raw provider bodies are never stored in either table.

### Notifications

- In-app `lead_notifications` table for assignment events.
- No email/SMS in this module; delivery failure must not block assignment.

### Public widget exposure

Widget lead capture responses return only safe public fields (reference, acknowledgement). Internal notes, assignment data, qualification rules, and audit metadata are excluded.

## Consequences

- Tenant Admins manage leads and counsellors under existing tenant layout with new sidebar entries.
- Counsellors have a separate workspace layout without tenant admin controls.
- Module 5 AI orchestration and Module 6 platform control plane remain unchanged.
- Original master-document lead qualification items (OTP, PIN validation, etc.) remain partially deferred within Module 7; core CRM workflow is delivered.
