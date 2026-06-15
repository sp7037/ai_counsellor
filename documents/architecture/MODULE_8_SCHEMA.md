# Module 8 Schema — Human Agent Live Conversations

**Last updated:** 2026-06-15  
**Status:** Complete

## Overview

Module 8 adds governed conversation operating modes, human handoff/ownership, counsellor messaging, widget polling sync, and tenant admin supervision. Real-time delivery uses bounded HTTP polling (no WebSocket/Reverb in this module).

## Migrations

### `2026_06_15_800001_create_human_conversation_module_tables`

**`conversations` additions:** `mode`, `human_owner_id`, `target_counsellor_id`, `handoff_request_uuid`, handoff/takeover timestamps, unread counters, `close_reason`, message timestamps.

**`messages` additions:** `sender_user_id`, `sender_display_name`, composite index `(conversation_id, id)`.

**`counsellor_profiles`:** `availability` (available/busy/offline).

**`lead_notifications`:** `conversation_id` nullable FK.

**`ai_runs`:** `purpose` (`response` default; `suggestion` reserved).

**New tables:** `conversation_handoffs`, `conversation_activities`, `conversation_read_states`.

### `2026_06_15_800002_complete_human_conversation_module_tables`

Completion migration for environments where `800001` partially applied; creates `conversation_read_states` if missing and adds short-named activity index.

## Enums

| Enum | Values |
|------|--------|
| `ConversationMode` | ai, handoff_requested, human, human_unavailable, closed |
| `MessageRole` | + counsellor (public widget visible) |
| `ConversationActivityType` | handoff_requested, handoff_claimed, ownership_transferred, … |
| `HandoffRecordStatus` | active, released, transferred |

## Services (`App\Services\Conversations`)

| Service | Responsibility |
|---------|----------------|
| `ConversationTransitionService` | Governed mode transitions |
| `ConversationHandoffService` | Request, claim, release, assign, close, reopen |
| `ConversationMessageService` | Counsellor send, visitor human-mode messages, public poll serialization |
| `ConversationDirectoryService` | Inbox pagination and metrics |
| `ConversationReadStateService` | Counsellor read markers and unread counts |
| `ConversationAccessService` | Authorization helpers |
| `ConversationActivityLogger` | Operational timeline |
| `ConversationNotificationService` | In-app notifications via `lead_notifications` |

## Routes

**Widget:** `POST /widget/v1/handoff`, `GET /widget/v1/messages/poll`

**Counsellor workspace:** `/app/{tenant}/workspace/conversations`, `/conversations/{conversation}`

**Tenant admin:** `/app/{tenant}/conversations`, `/conversations/{conversation}`

## Deferred

- Suggested replies (AI purpose field reserved; ADR-005)
- Conversation summaries
- WebSocket/broadcasting
- Counsellor availability admin UI (column exists; defaults used)

## Tests

`HumanAgentLiveConversationsTest` — 8 tests covering handoff idempotency, AI pause, widget poll, access control, lead conversion.
