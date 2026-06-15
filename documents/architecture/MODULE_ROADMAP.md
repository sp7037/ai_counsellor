# Module Roadmap

**Last updated:** 2026-06-15 (Module 5 complete)

## Principal reference

Module sequence and exit gates are defined in [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §16.

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

**Status:** Complete

- AI provider interface
- OpenAI adapter
- Future provider adapters
- Tool calling
- Knowledge retrieval
- Prompt versioning
- Usage and cost logs
- Guardrails

Exit gate: Model cannot bypass tool/service validation.

---

## Module 6 — Lead qualification

**Status:** Not Started

- Course interest
- PIN-code validation
- Qualification
- Passing year
- Subjects
- Study mode
- Study gap
- Mobile OTP
- Preliminary eligibility
- Duplicate lead control

Exit gate: Structured lead is produced from tested conversations.

---

## Module 7 — Human agent workspace

**Status:** Not Started

- Live conversations
- Human takeover
- Assignment
- Internal notes
- Suggested replies
- Follow-ups
- Conversation summaries

Exit gate: Agent can continue without losing context.

---

## Module 8 — Subscription and usage enforcement

**Status:** Not Started

- Monthly and annual plans
- AI token limits
- Conversation limits
- Agent-seat limits
- Expiry
- Grace period
- Suspension
- Usage reports

Exit gate: Suspension and limit enforcement proven.

---

## Later modules

**Status:** Not Started (all)

| Module | Scope |
|--------|-------|
| WhatsApp | Outbound messaging integration |
| Email | Transactional and notification email |
| Payments | Gateway orders and verification |
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
    → Module 6 → Module 7 → Module 8 → Integrations → Industry templates → Enterprise
```

Do not skip Module 1 (tenancy and suspension) before AI automation.
