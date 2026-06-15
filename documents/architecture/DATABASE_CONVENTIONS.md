# Database Conventions

**Last updated:** 2026-06-15 (Phase 0)

## Principal reference

Canonical entity map and relationships are defined in [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx). This document records coding and migration conventions for all future modules.

## Primary keys

- Use Laravel-standard **numeric auto-increment** primary keys (`id`) unless the architecture expressly requires UUIDs or another identifier.
- Public-facing resources must not expose insecure sequential identifiers when doing so creates enumeration or security risk.
- Use separate **public identifiers** (UUID, ULID, or rotatable public token) on resources exposed to visitors, widgets, or external APIs.

Examples from architecture:

- `leads.public_reference` — public-safe lead identifier
- `widget_keys` — rotatable public widget keys (never secrets)

## Multi-tenancy

### Tenant column

All tenant-owned tables must include:

```
tenant_id
```

### Isolation enforcement

Tenant isolation must be enforced through:

- Global query scopes or repository filters
- Middleware resolving tenant context
- Authorisation policies verifying tenant ownership
- Cache keys, queue payloads, storage paths, and exports scoped by tenant

**Never** depend only on a hidden form field or browser-submitted `tenant_id` for security.

### Unique indexes

Composite unique indexes should normally include `tenant_id`, for example:

```
UNIQUE (tenant_id, slug)
```

## Standard columns

Where suitable, use:

| Column | Purpose |
|--------|---------|
| `id` | Primary key |
| `tenant_id` | Tenant ownership (tenant-scoped tables) |
| `status` | Controlled lifecycle state |
| `created_by` | User who created the record |
| `updated_by` | User who last updated the record |
| `created_at` | Creation timestamp |
| `updated_at` | Last update timestamp |
| `deleted_at` | Soft delete (where appropriate) |

Do not add all columns blindly. Include only columns relevant to the entity.

## Money

- Store monetary values as **integers in the smallest currency unit** (e.g. paise, not floating-point rupees).
- Store the **currency code** separately (e.g. `INR`, `USD`).
- **Never** use binary floating-point (`FLOAT`, `DOUBLE`) for financial values.

Example:

```
amount_minor  INT UNSIGNED   -- 499900 = ₹4,999.00
currency      CHAR(3)      -- INR
```

## Time

- Store application timestamps in the database using Laravel conventions (`created_at`, `updated_at`, `deleted_at`).
- Application default timezone: **Asia/Kolkata**
- Display timestamps according to organisation or user timezone when UI requires it.
- Store tenant timezone on the `tenants` table (per architecture).

## Status values

- Use controlled **PHP enums**, database enums, lookup tables, or validated string constants.
- Do not scatter unexplained status strings throughout controllers.
- Document allowed transitions (e.g. subscription: `trial` → `active` → `grace` → `suspended`).

## Auditability

Security-sensitive and business-sensitive actions must eventually be auditable. Include immutable or append-only audit records for:

| Action category | Examples |
|-----------------|----------|
| Tenant lifecycle | Activation, suspension, reactivation |
| Subscription | Plan changes, expiry, grace transitions |
| Access control | Role and permission changes |
| AI configuration | Provider changes, budget changes |
| Knowledge | Publication, version approval |
| Leads | Reassignment, merge |
| Payments | State changes (gateway-verified only) |
| Conversations | Human takeover, escalation |
| Data governance | Export, deletion requests |

Audit table: `audit_logs` (global and tenant-scoped per architecture).

## Migrations

- All schema changes via Laravel migrations.
- Migrations must be **reversible** where technically possible (`down()` method).
- Never modify production data directly; use migrations and seeders.
- Foreign keys should reference `tenants.id` with appropriate `ON DELETE` behaviour documented per entity.

## Naming conventions

| Item | Convention |
|------|------------|
| Tables | snake_case, plural (`tenant_domains`) |
| Pivot tables | singular_singular alphabetical (`tenant_user`) |
| Models | singular PascalCase (`TenantDomain`) |
| Foreign keys | `{model}_id` (`tenant_id`, `assigned_user_id`) |

## Soft deletes

Use soft deletes (`deleted_at`) for tenant-owned business data where recovery or audit trail is required. Do not soft-delete immutable audit or transaction records.

## Encryption at rest

The following must be encrypted at the application or database layer:

- AI provider credentials (`tenant_ai_configs`)
- Integration connection secrets (`integration_connections`)
- Webhook secrets
- Messaging provider credentials

Never store plaintext secrets in migrations, seeders, or version control.
