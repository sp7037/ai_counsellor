# Module 2 Schema — Embeddable Chat Widget

**Last updated:** 2026-06-15  
**Module:** Module 2 — Embeddable chat widget  
**Status:** Complete

## Scope (from architecture)

Module 2 delivers the public embeddable chat widget foundation:

- Vanilla JavaScript embed script
- Rotatable tenant widget public keys
- Allowed-domain validation (server-side per session)
- Widget gateway API with signed session tokens
- Visitors, conversations, and messages storage
- Offline intake form when live assistance is unavailable
- Tenant admin UI for keys, domains, and minimal widget messages
- Demo pages for static HTML, PHP, and WordPress-style embeds

## Deliberate exclusions (deferred to later modules)

- AI provider calls (Module 5)
- Lead qualification and OTP (Module 6)
- Human agent workspace (Module 7)
- Full tenant branding / office hours / services config (Module 3)
- Redis, Reverb, WebSockets, production queues
- Custom tenant subdomain provisioning
- Payment gateways and subscriptions (Module 8)

## Ownership classification

| Table | Owner | Notes |
|-------|-------|-------|
| `tenant_widget_settings` | Tenant | Minimal welcome/offline copy for widget |
| `tenant_domains` | Tenant | Embed origin whitelist |
| `widget_keys` | Tenant | Public rotatable identifiers |
| `visitors` | Tenant | Pseudonymous browser identity |
| `conversations` | Tenant | Widget chat sessions |
| `messages` | Tenant | Immutable conversation messages |
| `widget_sessions` | Tenant | Gateway bearer tokens (hashed) |

Platform-owned tables from Module 1 are unchanged.

## Tables and relationships

```
tenants
  ├── tenant_widget_settings (1:1)
  ├── tenant_domains (1:n)
  ├── widget_keys (1:n)
  ├── visitors (1:n)
  ├── conversations (1:n) ── visitor
  │     └── messages (1:n)
  └── widget_sessions (1:n) ── conversation, visitor, widget_key
```

### `tenant_widget_settings`

- `tenant_id` (unique FK)
- `welcome_message`, `offline_message`
- `offline_form_enabled`

### `tenant_domains`

- `tenant_id`, `domain`, `status` (`pending|verified|blocked`)
- `verified_at`, `created_by`
- Unique `(tenant_id, domain)`

### `widget_keys`

- `uuid` (admin route binding)
- `tenant_id`, `public_key` (unique, embed identifier `wk_…`)
- `name`, `status` (`active|revoked`)
- `last_rotated_at`, `revoked_at`, `created_by`

### `visitors`

- `uuid`, `tenant_id`, `fingerprint_hash` (optional)
- `first_seen_at`, `last_seen_at`

### `conversations`

- `uuid`, `tenant_id`, `visitor_id`
- `channel` (`widget`), `status` (`open|closed`)
- `source_url`, `origin_domain`, `locale`
- `started_at`, `last_message_at`, `closed_at`
- `lead_id` nullable (reserved for Module 6)

### `messages`

- `uuid`, `tenant_id`, `conversation_id`
- `role` (`visitor|system|assistant|offline_intake`)
- `body`, `metadata` JSON, `created_at` (immutable)

### `widget_sessions`

- `uuid`, `tenant_id`, `conversation_id`, `visitor_id`, `widget_key_id`
- `origin_domain`, `token_hash`, `expires_at`, `last_used_at`

## Tenant resolution (public widget)

Public widget requests **never** accept `tenant_id` from the client.

Resolution path:

1. `widget_key` in JSON body (session start) or prior session token (subsequent calls)
2. Server validates widget key is active
3. Tenant must be in an active access state (`allowsTenantAccess()`)
4. `Origin` / `Referer` domain must match a **verified** `tenant_domains` row
5. Local dev may allow configured localhost origins (`WIDGET_ALLOW_LOCAL_ORIGINS`)

Authenticated tenant admin routes continue to use Module 1 `ResolveTenant` + `TenantContext`.

Widget gateway requests set `TenantContext::setFromWidgetGateway()` with `enforceIsolation()` for the duration of the request only. `ClearTenantContext` runs on the `api` middleware group.

## Services / actions

| Service | Responsibility |
|---------|----------------|
| `WidgetTenantResolver` | Validate widget key + origin + tenant status |
| `WidgetSessionService` | Start sessions, issue hashed tokens, default settings |
| `ConversationService` | Visitor messages, stub system reply, offline intake |
| `WidgetKeyService` | Create, rotate, revoke keys (audited) |
| `TenantDomainService` | Add, verify, remove domains (audited) |
| `TenantWidgetSettingsService` | Update welcome/offline copy |
| `OriginValidator` | Normalize and validate embed origins |

## Policies

| Policy | Rules |
|--------|-------|
| `WidgetKeyPolicy` | View: any active member; manage: owner/admin or platform super-admin |
| `TenantDomainPolicy` | View: any active member; manage: owner/admin or platform super-admin |
| `TenantWidgetSettingsPolicy` | View: any active member; update: owner/admin or platform super-admin |
| `ConversationPolicy` | View: any active member or platform super-admin |

## Audit events

| Action | Trigger |
|--------|---------|
| `widget_key.created` | New widget key |
| `widget_key.rotated` | Key rotation (old revoked, new created) |
| `widget_key.revoked` | Key revocation |
| `tenant_domain.created` | Domain added (pending) |
| `tenant_domain.verified` | Domain manually verified by admin |
| `tenant_domain.removed` | Domain deleted |

Widget gateway traffic is not audit-logged (high volume, no authenticated actor).

## Routes

### Public gateway (`/widget/v1`, `api` middleware group)

| Method | Path | Auth |
|--------|------|------|
| POST | `/widget/v1/session` | Widget key + Origin |
| GET | `/widget/v1/config` | Bearer session token |
| POST | `/widget/v1/messages` | Bearer session token |
| POST | `/widget/v1/offline` | Bearer session token |

CORS: `HandleWidgetCors` reflects request `Origin`. Rate limits via `config/widget.php`.

### Tenant admin (authenticated)

| Route | Component |
|-------|-----------|
| `/app/{tenant}/widget` | `tenant.widget.index` |
| `/app/{tenant}/widget/conversations` | `tenant.widget.conversations` |

## Widget embed

```html
<script async src="https://your-app/build/widget.js"
  data-widget-key="wk_…"
  data-gateway="https://your-app/widget/v1"></script>
```

Built by Vite entry `resources/js/widget/embed.js` → `public/build/widget.js`.

## Demo pages

- `public/widget-demo/static.html`
- `public/widget-demo/php/index.php`
- `public/widget-demo/wordpress.html`

## Status / lifecycle

| Resource | States | Notes |
|----------|--------|-------|
| Widget key | `active`, `revoked` | Rotation revokes old key |
| Tenant domain | `pending`, `verified`, `blocked` | Only `verified` allows production origins |
| Conversation | `open`, `closed` | Closed conversations reject new visitor messages |
| Tenant | Module 1 statuses | Suspended/cancelled/pending block widget sessions |

## Test coverage

| Suite | Focus |
|-------|-------|
| `WidgetGatewayTest` | Session start, messages, offline, suspended tenant, context cleanup |
| `WidgetAdministrationTest` | Keys, domains, settings authorization and audits |
| `WidgetIsolationTest` | Cross-tenant key/domain/conversation isolation |

Module 1 regression suites remain unchanged and passing.

## Module 3 extension

Module 3 extends `/widget/v1/session` and `/widget/v1/config` with a public `configuration` object (branding, catalogue, availability). See [MODULE_3_SCHEMA.md](MODULE_3_SCHEMA.md).

## Local configuration

See `config/widget.php`:

- `WIDGET_SESSION_TTL_MINUTES` (default 120)
- `WIDGET_ALLOW_LOCAL_ORIGINS` (default true for local dev)
- Rate limit strings for session and message endpoints

## Security controls

- No client-supplied `tenant_id`
- Session tokens stored as SHA-256 hashes only
- Origin mismatch rejected on authenticated widget calls
- `BelongsToTenant` global scope during gateway mutations
- Livewire admin actions authorize on every mutation; UUID/ID lookups are tenant-scoped (404 cross-tenant)
- Message body length limits enforced server-side
