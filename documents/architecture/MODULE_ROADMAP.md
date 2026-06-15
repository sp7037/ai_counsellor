# Module Roadmap

**Last updated:** 2026-06-15 (Module 7 complete)

## Principal reference

Module sequence and exit gates are defined in [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §16.

> **Roadmap reconciliation (2026-06-15):** The master document originally listed lead qualification as Module 6. Module 6 was delivered as **Super Admin Operations and Tenant Control Plane** (required for safe SaaS operations). The deferred lead-qualification scope is restored in **Module 7** together with the human agent workspace. Module numbering below preserves this history explicitly.

## Phase status legend

| Status | Meaning |
|--------|---------|
| **Complete** | Delivered and verified |
| **In Progress** | Active development |
| **Not Started** | Planned, not yet begun |
| **Deferred** | Explicitly postponed |

---

## Phase 0 — Project foundation

**Status:** Complete

Deliverables:

- Project audit and environment verification
- Laravel application installation (if needed)
- Architecture and agent documentation
- Technology decisions recorded
- Module 1 implementation plan prepared

Exit gate: Clean install and automated baseline checks.

---

## Module 1 — SaaS foundation

**Status:** Complete

- Super-admin authentication
- Tenant organisations
- Tenant domains
- Tenant users
- Roles and permissions
- Plans
- Subscriptions
- Activation and suspension
- Feature controls
- Audit logs

Exit gate: Automated tenant-isolation and authorisation tests pass.

---

## Module 2 — Embeddable chat widget

**Status:** Complete

- Public JavaScript widget (`public/build/widget.js`)
- Tenant widget public keys (rotatable)
- Domain validation (verified whitelist + local dev origins)
- Chat sessions, visitors, conversations, messages
- Offline intake form
- Widget gateway API with CORS and rate limits
- Tenant admin widget settings UI

Exit gate: Works on at least PHP/static/WordPress test pages (`public/widget-demo/`).

---

## Module 3 — Tenant configuration

**Status:** Complete

- Branding, logo, colours, widget position
- Assistant identity and AI disclosure
- Welcome/offline messages and languages
- Human-transfer settings (configuration only)
- Office hours with tenant timezone
- Services, courses, institutions, locations catalogues
- Public widget configuration API extension

Exit gate: Tenant admin can configure without code changes.

---

## Module 4 — Knowledge base

**Status:** Complete

- FAQs
- Structured course/service information
- Eligibility rules
- Fees
- Documents
- Published state
- Version tracking

Exit gate: Only published tenant content is retrievable.

---

## Module 5 — AI orchestration

**Status:** Complete (corrective security pass 2026-06-15)

- AI provider interface
- OpenAI adapter + fake test adapter
- Mandatory server-side idempotency and retry-safe run lifecycle
- Explicit credential ownership modes (tenant/platform)
- Tool calling (guarded retrieval only in this phase)
- Knowledge retrieval (tenant-scoped published-only)
- Prompt trust hierarchy and injection boundaries
- Usage and cost logs (`ai_runs`)
- Guardrails, secret redaction, and cross-tenant isolation tests

Exit gate: Model cannot bypass tool/service validation.

---

## Module 6 — Super Admin Operations and Tenant Control Plane

**Status:** Complete

Delivered in place of the master document's "lead qualification" slot to provide safe SaaS operations before lead workflows:

- Platform overview dashboard (real aggregates)
- Tenant directory with search, filter, pagination, detail tabs
- Tenant suspend/reactivate with reason, `suspended_by`, audit
- AI operations monitoring (safe metadata only)
- Usage reporting (tokens and runs — no fabricated cost)
- Audit log viewer (read-only)
- Platform settings (encrypted platform credential, no secret echo)
- System health checks
- Platform sidebar layout extending existing Flux theme
- Super Admin email verification UX
- Comprehensive authorization and isolation tests

**Lead qualification** (course interest, PIN validation, OTP, etc.) from the original master Module 6 list is **partially deferred** within Module 7 — core lead CRM, deterministic scoring, and counsellor workspace are delivered; OTP/PIN-specific flows remain for a later module.

Exit gate: Super Admin can operate tenants and monitor AI safely without weakening tenant isolation.

---

## Module 6 (original master doc) — Lead qualification items

**Status:** Partially restored in Module 7; remainder deferred

The master architecture §16 listed these under Module 6. They are **not erased** from history:

| Item | Module 7 status |
|------|-----------------|
| Structured lead from conversations | **Delivered** (widget capture, offline intake, manual, conversation link) |
| Qualification / preliminary eligibility | **Delivered** (deterministic engine; advisory score) |
| Duplicate lead control | **Delivered** (capture_event_uuid / source_reference idempotency) |
| Course interest | **Delivered** (service/programme interest fields) |
| PIN-code validation | **Deferred** |
| Passing year, subjects, study mode, study gap | **Deferred** (metadata extensibility only) |
| Mobile OTP | **Deferred** |

---

## Module 7 — Lead Qualification and Human Agent Workspace

**Status:** Complete

- Tenant-isolated lead model with governed lifecycle (stage, qualification, priority)
- Deterministic qualification engine with explainable score components
- Lead creation from widget capture, offline intake, and manual entry (idempotent)
- Conversation linkage (`conversation_id` / `conversations.lead_id`)
- Tenant Admin lead list, detail, filters, assignment
- Counsellor (Staff) management and profiles
- Assignment/reassignment history and in-app notifications
- Counsellor workspace (`/app/{tenant}/workspace/*`) — separate from platform and tenant admin
- Follow-up scheduling and counsellor workflow actions
- Lead activity timeline (separate from security audit log)
- Platform Super Admin excluded from routine lead handling

**Not in Module 7:** live human chat takeover, suggested replies, AI provider qualification calls, OTP/PIN flows, qualification rules admin UI, email/SMS notifications.

Exit gate: Tenant Admin can manage leads and counsellors; counsellors can work assigned leads without cross-tenant or cross-counsellor leakage.

---

## Module 8 — Human agent live conversations (formerly Module 7)

**Status:** Complete

Renumbered from original roadmap "Module 7 — Human agent workspace" live-conversation items:

- Governed conversation modes (AI / handoff / human / closed)
- Human handoff with idempotency and lead linkage
- Atomic counsellor ownership claiming
- Counsellor live messaging and workspace inbox
- Widget handoff endpoint and message polling (no WebSockets)
- Tenant admin conversation supervision
- Conversation-to-lead admin action
- Dashboard metrics integration (tenant + counsellor)
- In-app notifications for handoff events

**Deferred:** suggested replies, conversation summaries, WebSocket broadcasting.

Exit gate: Agent can continue live conversations without losing context.

---

## Module 9 — Subscription and usage enforcement

**Status:** Complete

- Platform plans and feature catalogue (`PlanFeature` enum)
- Manual subscription lifecycle (trial, active, grace, past due, expired, cancelled)
- `EntitlementResolver` with request-scoped cache
- AI usage reservation before provider calls
- Knowledge, lead, counsellor, and handoff limits
- Widget safe unavailable behaviour
- Tenant subscription page (accessible when expired)
- Platform plan and tenant subscription administration
- `subscriptions:maintain` scheduled command
- Payment gateway **deferred**

Exit gate: Suspension and limit enforcement proven.

**READY FOR MODULE 10 (Payments)**

## Module 10 — Payments

**Status:** Complete

- Razorpay integration (first production provider) with fake adapter for tests
- Provider-neutral payment services and governed order/payment states
- Plan pricing configuration (integer minor units, non-purchasable until configured)
- Tenant checkout, browser verification, webhook reconciliation
- Subscription activation via `SubscriptionLifecycleService` only
- Platform payment visibility and encrypted provider credentials
- Payment receipts (not tax invoices)

Exit gate: Verified payment activates subscription exactly once; entitlement boundary preserved.

**READY FOR MODULE 11**

## Module 11 — WhatsApp

**Status:** Complete

Roadmap scope: **Outbound messaging integration** (authoritative title: **WhatsApp**).

Delivered scope (broader than roadmap summary):

- Meta WhatsApp Cloud API via provider-neutral messaging layer
- Tenant-owned encrypted credentials and integration admin UI
- Webhook verification and signed inbound processing (`/webhooks/messaging/meta`)
- Inbound message → conversation/lead linkage, AI and human handoff reuse
- Outbound counsellor/AI replies with 24-hour session window enforcement
- Template metadata and sends outside the service window
- Delivery status webhooks (sent/delivered/read/failed)
- Platform integration health visibility (no tokens or message bodies)
- `whatsapp_integration` entitlement on Professional/Enterprise plans
- Fake provider for automated tests (`/webhooks/messaging/fake`)

Exit gate: Signed webhooks idempotent; cross-tenant isolation; entitlement enforcement; no unofficial automation.

**READY FOR MODULE 12 (Email)**

## Post–Module 11 integrations

**Status:** Not Started (remaining)

| Module | Scope |
|--------|-------|
| Email | Transactional and notification email |
| Appointments | Booking and availability |
| Document collection | Upload and processing workflows |
| Voice calling | Deferred per architecture |
| Healthcare-specific workflows | Administrative intake templates |
| Education-specific workflows | Consultant/college templates |
| Analytics and reporting | Usage, conversion, cost dashboards |

---

## Implementation sequence

```
Phase 0 (Complete) → Module 1 → Module 2 → Module 3 → Module 4 → Module 5
    → Module 6 (control plane) → Module 7 (leads + workspace) → Module 8 (live agent) → Module 9 (billing) → Integrations → Industry templates → Enterprise
```

Do not skip Module 1 (tenancy and suspension) before AI automation.
