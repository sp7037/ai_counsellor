# Module 3 Schema — Tenant Configuration

**Last updated:** 2026-06-15  
**Module:** Module 3 — Tenant configuration  
**Status:** Complete

## Scope (from architecture §16 and MODULE_ROADMAP.md)

Module 3 enables tenant administrators to configure the platform without code changes:

- Branding (display name, colours, logo, widget position)
- Assistant identity and AI disclosure
- Welcome/offline messages and languages
- Human-transfer settings (configuration only — not live agent workspace)
- Office hours with tenant timezone
- Services catalogue
- Courses catalogue
- Institutions
- Physical locations

**Exit gate:** Tenant admin can configure without code changes.

## Deliberate exclusions

- Knowledge base publication/versioning (Module 4)
- Fees, eligibility rules, documents, course_institution pivot (Module 4)
- AI orchestration (Module 5)
- Lead qualification and OTP (Module 6)
- Human agent workspace, queues, handover_requests workflow (Module 7)
- Subscriptions and billing (Module 8)
- Real-time presence or agent availability

## Ownership

| Table | Owner | Notes |
|-------|-------|-------|
| `tenant_settings` | Tenant | Branding, assistant, consent, human-transfer |
| `tenant_widget_settings` (extended) | Tenant | Widget position, welcome delay (messages remain here) |
| `tenant_office_hours` | Tenant | Weekly schedule |
| `services` | Tenant | Counselling categories |
| `courses` | Tenant | Course catalogue metadata |
| `institutions` | Tenant | Institution metadata |
| `locations` | Tenant | Branch/centre locations |
| `tenants.timezone`, `tenants.locale` | Tenant | Organisation defaults |

## Tables

### `tenant_settings`

- `display_name`, `assistant_name`, `assistant_title`
- `primary_color`, `accent_color` (hex)
- `logo_path` (storage path, not public URL in DB)
- `consent_text`, `consent_version`
- `ai_disclosure_enabled`, `ai_disclosure_message`
- `default_locale`, `supported_locales` (JSON array)
- `human_transfer_enabled`, `human_transfer_label`, `human_transfer_message`

### `tenant_office_hours`

- `day_of_week` (1=Monday … 7=Sunday)
- `opens_at`, `closes_at`, `is_closed`
- Unique `(tenant_id, day_of_week)`

### Catalogue tables (`services`, `courses`, `institutions`, `locations`)

- `uuid` public identifier
- `name`, `slug` (unique per tenant)
- `description`, status (`active`/`inactive`), `sort_order`
- Courses: `duration`, `study_mode`
- Institutions: `city`, `state`, `country`
- Locations: `address_line`, `city`, `state`, `pin_code`, `phone`

## Services

| Service | Responsibility |
|---------|----------------|
| `TenantConfigurationResolver` | Default settings bootstrap |
| `ConfigurationValidator` | Colours, locales, timezone, plain-text sanitization |
| `TenantBrandingService` | Branding updates, logo upload/removal |
| `TenantAssistantConfigurationService` | Assistant, messages, disclosure, human-transfer |
| `TenantOfficeHoursService` | Replace weekly schedule |
| `OfficeHoursEvaluator` | Availability using tenant timezone |
| `TenantCatalogueService` | CRUD for services/courses/institutions/locations |
| `WidgetPublicConfigService` | Public-safe widget configuration payload |

## Authorization

- **View:** any active tenant member
- **Manage:** tenant owner/admin or platform super-admin
- **Staff:** read-only access to configuration pages; mutations denied

## Widget public configuration

Extended `/widget/v1/session` and `/widget/v1/config` responses with `configuration` object containing:

- `branding`, `locale`, `messages`, `ai_disclosure`, `human_transfer`, `availability`, `catalogue`

Never exposes internal IDs, secrets, or private admin metadata.

## Production origin safety

`WIDGET_ALLOW_LOCAL_ORIGINS` defaults to `null`; when unset, localhost is allowed only in `local` and `testing` environments. Production must set `WIDGET_ALLOW_LOCAL_ORIGINS=false` explicitly or rely on fail-closed behaviour.

## Audit events

`configuration.updated`, `branding.updated`, `logo.updated`, `logo.removed`, `office_hours.updated`, and catalogue lifecycle events (`service.*`, `course.*`, `institution.*`, `location.*`).

## Routes

| Route | Page |
|-------|------|
| `/app/{tenant}/configuration` | Hub |
| `/app/{tenant}/configuration/branding` | Branding and logo |
| `/app/{tenant}/configuration/assistant` | Assistant, languages, messages |
| `/app/{tenant}/configuration/office-hours` | Weekly schedule |
| `/app/{tenant}/configuration/services` | Services |
| `/app/{tenant}/configuration/courses` | Courses |
| `/app/{tenant}/configuration/institutions` | Institutions |
| `/app/{tenant}/configuration/locations` | Locations |

## Validation limits

See `config/configuration.php` — max 50 catalogue items per type, logo max 2 MB (JPEG/PNG/WebP only), plain-text sanitization on all user-facing strings.

## Tests

- `TenantConfigurationTest`
- `ConfigurationIsolationTest`
- `WidgetPublicConfigTest` (includes production localhost rejection)
