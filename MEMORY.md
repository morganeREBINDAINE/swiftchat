# Memory Index — SwiftChat

- [SwiftChat roadmap progress](#roadmap-progress) — Phase 1 done, Phase 2 in progress (cursor pagination still needed)

---

## Roadmap progress

Last updated: 2026-05-09

### Phase 1 — Foundations ✅ Complete

All items done: Docker setup, DATABASE_URL, dependencies installed, entities (User/Conversation/Message), migrations, security config, registration + email verification, login + remember_me, fixtures (alice/bob/charlie), functional tests for registration/login/email verification.

### Phase 2 — Core Messaging 🔶 In progress

Done:
- `ConversationService::findOrCreate()` + unit tests
- `ConversationController` (list, new, show, markAsRead) + functional tests
- `MessageController::send()` (POST, JSON, rate-limited, CSRF) + functional tests
- `ConversationVoter` (VIEW + SEND) + unit tests
- `MessageRepository::findByConversation()` (basic, limit 50) + `markAllAsReadBy()` (bulk DQL UPDATE)
- Twig templates: messaging layout, sidebar, message window
- Design decision: markAsRead is conversation-level (`PATCH /conversations/{id}/read`), not per-message — single DB update + single Mercure event in Phase 3

**Still needed:**
- `MessageRepository::findBeforeId()` — cursor pagination
- Tests for markAsRead (success + non-participant 403)
