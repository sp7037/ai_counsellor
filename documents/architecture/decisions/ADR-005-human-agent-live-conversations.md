# ADR-005: Human agent live conversations

**Status:** Accepted  
**Date:** 2026-06-15  
**Context:** Module 8 — Human Agent Live Conversations

## Decisions

### Conversation operating modes

`ConversationMode` governs AI vs human behaviour: `ai`, `handoff_requested`, `human`, `human_unavailable`, `closed`. Transitions are enforced by `ConversationTransitionService`; controllers and UI cannot set mode directly.

### Human ownership and concurrency

- Current owner: `conversations.human_owner_id`
- History: `conversation_handoffs` with `is_current` flag
- Claims use `lockForUpdate()` inside a DB transaction
- Only one active owner per conversation

### AI pause/resume context policy

- **Human / handoff_requested:** no AI provider calls for visitor messages
- **AI resume:** explicit transition via `ConversationHandoffService::release()` or admin action
- **Provider history:** visitor + assistant + counsellor messages (counsellor prefixed `[Human counsellor]` in prompt builder); system fallbacks and internal notes excluded

### Message sender classification

`MessageRole::Counsellor` with `sender_user_id` and `sender_display_name` (safe public name only). Distinct from `assistant` and `system`.

### Polling vs broadcasting

**Bounded HTTP polling** at 5s (widget JS + Livewire `wire:poll.5s`). No Laravel Echo/Reverb installed. Documented in `config/conversations.php`.

### Lead assignment vs conversation ownership

- Claiming a waiting conversation may auto-assign an unassigned linked lead via `LeadAssignmentService`
- Lead reassignment does not silently transfer live ownership; admin must assign `target_counsellor_id` or counsellor must claim
- Lead and conversation activities remain separate tables

### Read-state model

`conversation_read_states` per counsellor user plus denormalized `counsellor_unread_count` on conversation for inbox performance.

### Suggested replies

**Deferred.** `ai_runs.purpose` supports future `suggestion` runs without mixing them with response runs.

### Deduplication

Handoff idempotency via `(tenant_id, handoff_request_uuid)`. Visitor/counsellor message idempotency via existing `messages.request_uuid`.

## Consequences

- Counsellors work live conversations in `/workspace/conversations/*` without platform access
- Widget receives human messages through poll endpoint; internal data never exposed
- Module 9 (subscription enforcement) not started
