# Agent Instructions

**Applies to:** Cursor, Codex, and all future development agents working on AI Counsellor.

## Before you change any code

1. **Read the master architecture** at `documents/architecture/AI_COUNSELLOR_MASTER_ARCHITECTURE.docx`.
2. **Read** [IMPLEMENTATION_STATUS.md](../architecture/IMPLEMENTATION_STATUS.md) for current phase and completed work.
3. **Inspect existing migrations and tests** in `database/migrations/` and `tests/`.
4. **Work on only the assigned module** — do not implement future modules or unrelated features.
5. **Avoid unrelated refactoring** — minimise scope to the task at hand.

## Mandatory rules

| # | Rule |
|---|------|
| 1 | Read the master architecture before changing code |
| 2 | Read `IMPLEMENTATION_STATUS.md` before changing code |
| 3 | Inspect existing migrations and tests |
| 4 | Work on only the assigned module |
| 5 | Avoid unrelated refactoring |
| 6 | Never expose secrets or API keys |
| 7 | Preserve strict tenant isolation in database, cache, queues, storage, and events |
| 8 | Use migrations for all database changes |
| 9 | Add rollback support where technically possible |
| 10 | Add validation, authorisation, and automated tests |
| 11 | Never deploy without explicit instruction |
| 12 | Never modify production data |
| 13 | List all changed files in completion reports |
| 14 | List all database changes in completion reports |
| 15 | Provide exact local testing steps |
| 16 | Report failures and unresolved risks honestly |
| 17 | Update implementation documentation after each phase |

## Tenant isolation

- Every tenant-owned table must include `tenant_id`.
- Never accept `tenant_id` from an untrusted browser request as authority.
- Apply global scopes, policies, or repository filters consistently.
- Write tenant-isolation tests for every module touching tenant data.

## Architecture changes

- Do not change established architecture silently.
- Create an Architecture Decision Record (ADR) in `documents/architecture/decisions/` for material changes to tenancy, database strategy, AI contract, authentication, billing, public API, storage, queue/real-time, or security boundaries.

## Security

Follow [SECURITY_BASELINE.md](../architecture/SECURITY_BASELINE.md) without exception.

## Database

Follow [DATABASE_CONVENTIONS.md](../architecture/DATABASE_CONVENTIONS.md) for all migrations and models.

## Completion report

After each module or phase, produce a report using [CHANGE_REPORT_TEMPLATE.md](CHANGE_REPORT_TEMPLATE.md) and update [IMPLEMENTATION_STATUS.md](../architecture/IMPLEMENTATION_STATUS.md).

## Repository structure (target)

Per master architecture §21, organise domain code under:

```
app/
  Domain/
    Tenancy/
    Conversations/
    Leads/
    Knowledge/
    AI/
    Billing/
    Integrations/
```

Introduce this structure incrementally as modules are implemented; do not reorganise prematurely in unrelated phases.

## Prohibited without explicit instruction

- Production deployment
- OpenAI or AI provider integration (Module 5)
- Chatbot widget (Module 2)
- Payment, WhatsApp, email, or voice integrations
- Hard-coding organisation or industry names
- Client-specific code forks when configuration suffices
