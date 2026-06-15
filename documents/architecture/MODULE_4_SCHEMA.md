# Module 4 — Knowledge Base Schema

**Last updated:** 2026-06-15 (Module 4 — complete)

## Principal reference

- [AI_COUNSELLOR_MASTER_ARCHITECTURE.docx](AI_COUNSELLOR_MASTER_ARCHITECTURE.docx) §8 (entities), §10 (governance), §16 (exit gate)
- [MODULE_ROADMAP.md](MODULE_ROADMAP.md) — Module 4 definition

## Module scope

**Title:** Module 4 — Knowledge base

**Objective:** Tenant-managed knowledge content with publication governance, version history, structured fees and eligibility, source documents, and course–institution availability — retrievable only when published.

**Exit gate:** Only published tenant content is retrievable (widget search and future Module 5 retrieval contract).

## Ownership classification

| Resource | Ownership | Public exposure |
|----------|-----------|-----------------|
| `knowledge_items` (draft fields) | Tenant-owned private | Never |
| `knowledge_items` (published via `current_version_id`) | Tenant-owned public when status = `published` | Widget search excerpt only |
| `knowledge_versions` | Tenant-owned immutable history | Indirect via published item search |
| `knowledge_fees` | Tenant-owned | Admin only (not widget API in Module 4) |
| `eligibility_rules` | Tenant-owned | Admin only |
| `documents` | Tenant-owned private files | Never via widget; authorized download only |
| `course_institution` | Tenant-owned | Admin only |
| Audit metadata | Platform/tenant internal | Never |

`tenant_id` is always derived from `TenantContext` / authenticated membership — never from client input.

## Tables and relationships

### `knowledge_items`

| Column | Notes |
|--------|-------|
| `uuid` | Public identifier |
| `tenant_id` | FK → `tenants`, restrict on delete |
| `type` | `KnowledgeItemType` enum |
| `status` | `draft`, `published`, `archived` |
| `locale` | ISO-style locale string |
| `title` | Display title (mirrors current published title when published) |
| `draft_title`, `draft_body` | Editable draft content |
| `current_version_id` | FK → `knowledge_versions`, nullable |
| `service_id`, `course_id`, `institution_id`, `location_id` | Optional FKs to Module 3 catalogues |
| `published_at`, `archived_at` | Lifecycle timestamps |
| `created_by`, `updated_by` | FK → `users` |

Indexes: `(tenant_id, status, type)`, `(tenant_id, locale)`.

### `knowledge_versions`

Immutable published snapshots.

| Column | Notes |
|--------|-------|
| `uuid` | Public identifier |
| `knowledge_item_id` | FK, restrict on delete |
| `version_number` | Unique per item |
| `title`, `body` | Published content at time of publish |
| `content_checksum` | SHA-256 of body for change detection |
| `published_at`, `published_by` | Publication audit |

Unique: `(knowledge_item_id, version_number)`.

### `knowledge_fees`

| Column | Notes |
|--------|-------|
| `label`, `fee_type` | `exact`, `starting_from`, `range` |
| `amount_minor`, `amount_max_minor` | Integer minor units (no floats) |
| `currency` | ISO 4217 (INR, USD, GBP, EUR) |
| `service_id`, `course_id`, `institution_id`, `knowledge_item_id` | Optional catalogue links |
| `effective_from`, `effective_until` | Optional date range |
| `status` | `draft`, `published`, `archived` |

### `eligibility_rules`

| Column | Notes |
|--------|-------|
| `title` | Rule label |
| `required_criteria`, `preferred_criteria` | Bounded plain text (no executable expressions) |
| `priority` | Ordering for admin display |
| `service_id`, `course_id` | Optional catalogue links |
| `status` | `draft`, `published`, `archived` |

### `documents`

| Column | Notes |
|--------|-------|
| `display_name` | Sanitized original name |
| `storage_path` | Private `local` disk path |
| `mime_type`, `size_bytes`, `checksum` | Safe metadata |
| `knowledge_item_id` | Optional association |
| `status` | `stored` |

Allowed MIME types: PDF, DOC, DOCX, TXT. Stored under `knowledge-documents/{tenant_uuid}/`.

### `course_institution`

Pivot linking Module 3 `courses` and `institutions`.

| Column | Notes |
|--------|-------|
| `intake_label` | e.g. "September 2026" |
| `fee_amount_minor`, `currency` | Optional fee at offering level |
| `notes` | Bounded text |
| `status` | `draft`, `published`, `archived` |

Unique: `(tenant_id, course_id, institution_id)`.

## Lifecycle and versioning

### Knowledge items

1. **draft** — editable; not searchable publicly.
2. **published** — `publish()` creates immutable `knowledge_versions` row, sets `current_version_id`, copies draft to version.
3. Editing a published item resets status to **draft** in draft fields; republish creates new version number.
4. **archived** — excluded from search; not editable.

Publication and archival are transactional with audit logging. `version_number` increments monotonically; unique constraint prevents duplicates.

### Fees, eligibility, course_institution

Simpler lifecycle: `draft` → `published` → `archived`. No version table (single-record publish).

## Content sanitization

- All knowledge body/title content passes through `KnowledgeContentSanitizer` → `ConfigurationValidator::sanitizePlainText`.
- Scripts, event handlers, `javascript:` URLs, and unsafe HTML are stripped.
- Plain text only in Module 4 (no rich HTML editor).
- Widget search returns sanitized published excerpts (max 280 chars).

## Search and retrieval

**Contract:** `App\Contracts\Knowledge\KnowledgeRetrievalContract`

**Implementation:** `PublishedKnowledgeSearchService`

- Tenant-scoped SQL `LIKE` search on published items with `current_version_id`.
- Wildcards escaped; query length bounded (`max_search_query_length` = 120).
- Results paginated by `max_search_results` (20).
- Returns: `uuid`, `type`, `locale`, `title`, `excerpt`, `version_number`.
- Excludes: drafts, archived, storage paths, internal IDs, staff identities.

**Widget endpoint:** `GET /widget/v1/knowledge/search?q=…` (requires valid widget session + origin).

Module 5 will consume the same contract for AI retrieval — no external provider calls in Module 4.

## Services and actions

| Service | Responsibility |
|---------|----------------|
| `KnowledgeItemService` | createDraft, updateDraft, publish, archive, deleteItem |
| `KnowledgeFeeService` | CRUD, publish, archive |
| `EligibilityRuleService` | CRUD, publish, archive |
| `KnowledgeDocumentService` | upload, remove (MIME/size/extension validation) |
| `CourseInstitutionService` | CRUD, publish, archive |
| `PublishedKnowledgeSearchService` | searchPublished |
| `KnowledgeContentSanitizer` | Plain-text sanitization |

## Policies and authorization

| Role | View knowledge | Manage (create/edit/publish/archive/delete/upload) |
|------|----------------|-----------------------------------------------------|
| Platform super-admin | Yes | Yes |
| Tenant owner | Yes | Yes |
| Tenant admin | Yes | Yes |
| Tenant staff | Yes | No |
| Guest / non-member | No | No |

Gates: `viewTenantKnowledge`, `manageTenantKnowledge`.

Per-resource policies: `KnowledgeItemPolicy`, `KnowledgeFeePolicy`, `EligibilityRulePolicy`, `KnowledgeDocumentPolicy`, `CourseInstitutionPolicy`.

## Audit events

| Action | Event |
|--------|-------|
| Knowledge create/update/publish/version/archive/delete | `knowledge.*` |
| Source document upload/remove | `knowledge.source_uploaded`, `knowledge.source_removed` |
| Fee create/update/archive | `fee.*` |
| Eligibility create/update/archive | `eligibility.*` |
| Course-institution create/update | `course_institution.*` |

Audit metadata excludes document contents and full body text.

## Routes and admin UI

Prefix: `/app/{tenant}/knowledge`

| Route | Page |
|-------|------|
| `/` | Knowledge index |
| `/items` | Knowledge items (draft/edit/publish/archive) |
| `/fees` | Fee management |
| `/eligibility` | Eligibility rules |
| `/documents` | Source document upload |
| `/course-institutions` | Course–institution offerings |
| `/documents/{document}/download` | Authorized private download |

## Widget / public API changes

- New: `GET /widget/v1/knowledge/search` — published knowledge search only.
- Existing widget session, origin, and key validation unchanged.
- Module 3 `/widget/v1/config` unchanged (no knowledge dump in config response).

## Test coverage

`tests/Feature/KnowledgeBaseTest.php` (10 tests):

- Staff cannot create knowledge
- Admin publish + audit + version creation
- Republish creates new version
- XSS/script stripping
- Cross-tenant item selection denied (404)
- Draft not in widget search
- Published content in widget search (no internal fields)
- Document upload rejects invalid MIME
- Archived content not searchable
- Production localhost rejection for widget session

`AuthenticatedHttpSmokeTest` extended for knowledge admin pages.

**Total suite:** 102 tests, 237 assertions.

## Exclusions (Module 4 boundary)

- OpenAI / Gemini / Claude / DeepSeek calls
- Vector embeddings and vector databases
- AI answer generation and prompt orchestration
- OCR, PDF text extraction, document parsing
- Lead qualification, OTP, human-agent workspace
- Redis, Reverb, WebSockets
- Payment plans, subscriptions, invoices
- Semantic / AI search
- Automated eligibility decisions
- Rich HTML CMS

## Deferred to Module 5

- AI provider adapters and tool calling
- Knowledge retrieval integration in chat responses
- Prompt versioning and guardrails
- Usage and cost logging for AI calls
