# Module 7 Schema — Lead Qualification and Human Agent Workspace

**Last updated:** 2026-06-15  
**Status:** Complete

## Overview

Module 7 delivers tenant-isolated lead management, deterministic qualification, counsellor (Staff role) workspace, assignment history, follow-ups, and in-app notifications. Platform Super Admin remains outside routine tenant lead workflows (`LeadPolicy` denies platform users).

Roadmap reconciliation: Module 6 delivered the Super Admin control plane (replacing the master document's original Module 6 slot). Deferred lead qualification and human agent workspace are restored together as **Module 7**.

## New migration

### `2026_06_15_700001_create_leads_module_tables`

#### `leads`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Internal |
| `uuid` | uuid, unique | Public route binding |
| `public_reference` | string(32) | Human-readable reference; unique per tenant |
| `tenant_id` | FK → `tenants` | Tenant isolation |
| `conversation_id` | FK → `conversations`, nullable | Linked visitor conversation |
| `source` | string(40) | `LeadSource` enum |
| `source_reference` | string(120), nullable | Idempotency for integrations |
| `capture_event_uuid` | uuid, nullable | Widget capture idempotency |
| `created_by` | FK → `users`, nullable | Manual creator |
| Contact / enquiry fields | strings/text | Name, mobile, email, location, interests, summary |
| `lead_score` | smallint | Deterministic advisory score (0–100) |
| `qualification_status` | string(40) | `LeadQualificationStatus` enum |
| `stage` | string(40) | `LeadStage` enum |
| `priority` | string(20) | `LeadPriority` enum |
| `assigned_to` | FK → `users`, nullable | Current counsellor |
| `assigned_at` | timestamp, nullable | |
| `next_follow_up_at` | timestamp, nullable | Denormalised for list queries |
| `last_contacted_at` | timestamp, nullable | |
| `closed_at` | timestamp, nullable | |
| `lost_reason` / `invalid_reason` | string, nullable | Required for terminal outcomes |
| `ai_suggested_*` | nullable | AI suggestions (advisory only; not implemented as provider calls in this module) |
| `score_components` | json, nullable | Explainable deterministic factors |
| `metadata` | json, nullable | Non-query-critical extras |

**Unique constraints:** `(tenant_id, public_reference)`, `(tenant_id, capture_event_uuid)`, `(tenant_id, source, source_reference)`  
**Indexes:** `(tenant_id, stage|qualification_status|priority|assigned_to|next_follow_up_at|created_at)`

#### `lead_assignments`

Immutable assignment history with `is_current` flag. One current assignment per lead.

#### `lead_activities`

Operational timeline (stage changes, notes, contact attempts, etc.). Separate from security `audit_logs`.

#### `lead_notes`

Internal tenant notes; never exposed via widget API.

#### `lead_follow_ups`

Scheduled follow-ups with status (`FollowUpStatus` enum), due date, completion outcome.

#### `lead_qualification_rules`

One row per tenant (`tenant_id` unique) with JSON rule weights. Changes audited.

#### `counsellor_profiles`

Extends `tenant_user` Staff memberships with mobile, designation, max capacity, timezone.

#### `lead_notifications`

In-app notifications per user/tenant (assignment, reassignment). Delivery failure does not block workflows.

#### `conversations.lead_id`

FK added to existing `lead_id` column from Module 2.

## Enums (`App\Enums\Leads`)

| Enum | Purpose |
|------|---------|
| `LeadSource` | widget_conversation, widget_form, manual, api, … |
| `LeadStage` | Governed operational stages |
| `LeadQualificationStatus` | not_reviewed, potential, qualified, unqualified, insufficient_information |
| `LeadPriority` | low, normal, high, urgent |
| `LeadActivityType` | created, assigned, stage_changed, note_added, … |
| `FollowUpStatus` | scheduled, completed, missed, cancelled, rescheduled |

## Services (`App\Services\Leads`)

| Service | Responsibility |
|---------|----------------|
| `LeadCreationService` | Create leads with idempotency; conversation conversion |
| `LeadCaptureService` | Widget capture + offline intake integration |
| `LeadQualificationEngine` | Deterministic scoring; no sensitive attributes |
| `LeadTransitionService` | Allowed stage transitions; admin override path |
| `LeadAssignmentService` | Assign/reassign with history and notifications |
| `LeadWorkflowService` | Notes, follow-ups, contact attempts, terminal outcomes |
| `LeadActivityLogger` | Append operational timeline events |
| `LeadDirectoryService` | Paginated lists, tenant/counsellor metrics |
| `CounsellorManagementService` | Staff user + profile lifecycle |

## Authorization

| Component | Scope |
|-----------|-------|
| `LeadPolicy` | Owner/Admin manage all tenant leads; Staff view/update assigned only; platform super admin denied |
| `EnsureTenantLeadManager` | Owner/Admin tenant lead admin routes |
| `EnsureCounsellorWorkspace` | Staff workspace routes only |
| Gates | `manageTenantLeads`, `workCounsellorLeads` |

Counsellors use existing `TenantRole::Staff` membership — no duplicate auth table.

## Routes

### Tenant admin (`/app/{tenant}/…`, middleware `tenant.lead.manager`)

| Route name | Path |
|------------|------|
| `tenant.leads.index` | `/leads` |
| `tenant.leads.create` | `/leads/create` |
| `tenant.leads.show` | `/leads/{lead}` |
| `tenant.counsellors.index` | `/counsellors` |
| `tenant.counsellors.create` | `/counsellors/create` |

### Counsellor workspace (`/app/{tenant}/workspace/…`, middleware `counsellor.workspace`)

| Route name | Path |
|------------|------|
| `workspace.dashboard` | `/` |
| `workspace.leads.index` | `/leads` |
| `workspace.leads.show` | `/leads/{lead}` |
| `workspace.follow-ups.index` | `/follow-ups` |

### Widget (`POST /widget/v1/leads`)

Requires valid widget session + origin. Rate-limited with widget message throttle. Idempotent via `capture_event_uuid`.

## Activity vs audit

| Store | Purpose |
|-------|---------|
| `lead_activities` | Operational timeline visible to authorised tenant users on lead detail |
| `audit_logs` | Security/compliance events via `AuditLogger` (counsellor lifecycle, assignment, qualification rule changes) |

## Deferred within Module 7 boundary

- AI-assisted qualification provider calls (deterministic engine only; AI suggestion fields reserved)
- PIN/OTP/mobile verification flows from original master Module 6 list
- Qualification rules admin UI (table + service exist; defaults used)
- Conversation-to-lead UI button (service method `fromConversation` exists)
- Email/SMS notification delivery
- Live human chat takeover (Module 8+ scope per roadmap)

## Test coverage

`LeadQualificationWorkspaceTest` — role boundaries, idempotency, isolation, qualification safety, suspended tenant blocking.

**Full suite:** 174+ tests after Module 7.
