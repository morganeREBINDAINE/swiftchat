# ROADMAP.md — SwiftChat
## Technical architecture & development plan

> This document is a living reference. Phases and priorities may be reordered as the project evolves.

---

## Entity Schema

```
User
├── id (uuid, v7)
├── username (string, unique)
├── email (string, unique)
├── password (hashed)
├── avatar (string, nullable)
├── roles (json)
├── is_verified (bool, default: false)
├── email_notifications_enabled (bool, default: true)
├── last_seen_at (datetime, nullable)
└── created_at (datetime)

Conversation
├── id (uuid, v7)
├── participant_1 (FK → User)
├── participant_2 (FK → User)
├── created_at (datetime)
└── updated_at (datetime)   ← updated on each new message

Message
├── id (uuid, v7)
├── conversation (FK → Conversation)
├── sender (FK → User)
├── content (text)
├── created_at (datetime)
└── read_at (datetime, nullable)

EmailVerificationToken
├── id (uuid, v7)
├── user (FK → User)
├── token (string, unique)
└── expires_at (datetime)
```

---

## Phase 1 — Foundations (Week 1)

**Goal: installable project, database ready, working authentication**

- [x] Clone & start `dunglas/symfony-docker` (already done)
- [x] Configure `DATABASE_URL` in `.env.local`
- [x] Install dependencies:
    - `symfony/mercure-bundle`
    - `symfony/messenger`
    - `symfony/doctrine-messenger`
    - `symfony/mailer`
    - `symfony/uid`
    - `symfonycasts/verify-email-bundle`
- [x] Entities: `User`, `Conversation`, `Message`, `EmailVerificationToken`
- [x] Initial migrations
- [x] Symfony Security configuration (provider, hasher, firewall, form_login)
- [x] `RegistrationFormType` + controller + verification email
- [x] Login + remember_me
- [x] Base fixtures (2–3 test users)
- [x] Tests: registration, login, email confirmation

---

## Phase 2 — Core Messaging (Week 2)

**Goal: send and receive messages, persistence, paginated loading**

- [x] `ConversationService::findOrCreate(User $a, User $b)`
- [x] `ConversationController`: conversation list + new conversation
- [x] `MessageController::send()` (POST, returns JSON)
- [x] `ConversationController::markAsRead()` (PATCH `/conversations/{id}/read` — bulk-marks all unread messages from the other participant; single DB update + single Mercure event in Phase 3)
- [x] `ConversationVoter`: only participants can access a conversation
- [x] Cursor pagination in `MessageRepository::findBeforeId()`
- [x] Twig templates: messaging layout, sidebar, message window
- [x] Rate limiting on send (RateLimiter component)
- [x] Tests: send message, access denied, mark as read (success + non-participant 403)

---

## Phase 3 — Mercure Real-time (Week 3)

**Goal: live messages, typing indicator, presence status**

- [x] Confirm Mercure Hub is reachable (FrankenPHP built-in — check `MERCURE_URL` env)
- [x] `MercurePublisher::publishMessage(Message $message)`
- [x] `MercurePublisher::publishTyping(User $user, Conversation $conv)`
- [x] JWT subscriber claims scoped per conversation topic
- [x] `chat_controller.js`: EventSource → listen to topic, append message to DOM
- [x] `typing_controller.js`: debounce input → POST `/typing`, listen SSE
- [x] `PresenceService`: Redis-based presence (TTL 90 s online / 3 min away), `PresenceCleanupMessage` set with Scheduler to pass offline inactive users
- [x] `presence_controller.js`: heartbeat every 60 s, idle/hidden → away, `sendBeacon` on `beforeunload`
- [x] `peer_presence_watcher.js`: EventSource on `presence/{userId}`, 3 s offline delay
- [x] Presence dot displayed in conversation list (static, Redis-fetched) and chat header (real-time Mercure)
- [x] Unread badge updated in real time (sidebar badges via `conversations.js`, ✓→✓✓ via `chat-controller.js`)
- [x] EventSource cleanup on conversation switch — `pagehide` calls `hub.close()` which closes the EventSource and clears all handlers; full-page navigation means no SPA-style orphan risk
- [x] Tests: mocked Mercure publish, presence update

---

## Phase 4 — Async Email Notifications (Week 4)

**Goal: email sent if message unread after 5 minutes**

- [x] `NotifyUnreadMessageMessage` (readonly DTO: `messageId`, `recipientId`)
- [x] Dispatch in `MessageController::send()` with `DelayStamp(300_000)`
- [x] `NotifyUnreadMessageHandler`:
    - Fetch `Message` by ID
    - Check `read_at === null`
    - Check `email_notifications_enabled === true`
    - Check `is_verified === true`
    - Send via `MailerInterface`
    - Log the send
- [x] HTML + plain text email templates
- [x] Messenger transport config (Doctrine) + routing in `messenger.yaml`
- [ ] Mailer DSN config (Brevo) in `.env.local`
- [ ] Test worker locally: `docker compose exec php bin/console messenger:consume async`
- [x] Unit tests on handler (all 5 cases)

---

## Phase 5 — Profile & Admin (Week 5)

**Goal: user profile page, basic admin dashboard**

- [x] `ProfileController`: edit username/email/avatar/password
- [x] Avatar upload (VichUploaderBundle: non-mapped transit property + mapped `avatar`, lifecycle callback to reset after flush)
- [x] `email_notifications_enabled` toggle
- [x] `AdminController`: user list, enable/disable account
- [x] `ROLE_ADMIN` protection + admin fixture
- [x] Basic stats (messages today, active users)

---

## Phase 6 — Quality & Deployment (Week 6)

**Goal: deployed, tested, documented**

- [ ] Complete test suite (critical WebTestCase + PHPUnit unit tests)
- [ ] Production environment variables (`.env.prod` / Docker secrets)
- [ ] Supervisor config for the Messenger worker (or FrankenPHP worker mode)
- [ ] CI (GitHub Actions: lint, tests)
- [ ] README with local setup + Docker instructions
- [ ] Demo fixtures for portfolio presentation

---

## Known Risks

- **Mercure JWT in dev**: the built-in hub requires correct `MERCURE_JWT_SECRET` env — double-check `docker-compose.override.yml`
- **DelayStamp**: requires Doctrine transport (not `sync`); verify `messenger.yaml` routing before testing
- **EventSource cleanup**: JS listeners must be closed on conversation switch to avoid memory leaks
- **UUID v7**: chronologically sortable — prefer over v4 for messages (allows `ORDER BY id`)
