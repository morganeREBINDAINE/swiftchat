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
├── presence_status (enum: online|offline|away)
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

- [ ] Clone & start `dunglas/symfony-docker` (already done)
- [ ] Configure `DATABASE_URL` in `.env.local`
- [ ] Install dependencies:
    - `symfony/mercure-bundle`
    - `symfony/messenger`
    - `symfony/doctrine-messenger`
    - `symfony/mailer`
    - `symfony/uid`
    - `symfonycasts/verify-email-bundle`
- [ ] Entities: `User`, `Conversation`, `Message`, `EmailVerificationToken`
- [ ] Initial migrations
- [ ] Symfony Security configuration (provider, hasher, firewall, form_login)
- [ ] `RegistrationFormType` + controller + verification email
- [ ] Login + remember_me
- [ ] Base fixtures (2–3 test users)
- [ ] Tests: registration, login, email confirmation

---

## Phase 2 — Core Messaging (Week 2)

**Goal: send and receive messages, persistence, paginated loading**

- [ ] `ConversationService::findOrCreate(User $a, User $b)`
- [ ] `ConversationController`: conversation list + new conversation
- [ ] `MessageController::send()` (POST, returns JSON)
- [ ] `ConversationController::markAsRead()` (PATCH `/conversations/{id}/read` — bulk-marks all unread messages from the other participant; single DB update + single Mercure event in Phase 3)
- [ ] `ConversationVoter`: only participants can access a conversation
- [ ] Cursor pagination in `MessageRepository::findBeforeId()`
- [ ] Twig templates: messaging layout, sidebar, message window
- [ ] Rate limiting on send (RateLimiter component)
- [ ] Tests: send message, access denied, mark as read (success + non-participant 403)

---

## Phase 3 — Mercure Real-time (Week 3)

**Goal: live messages, typing indicator, presence status**

- [ ] Confirm Mercure Hub is reachable (FrankenPHP built-in — check `MERCURE_URL` env)
- [ ] `MercurePublisher::publishMessage(Message $message)`
- [ ] `MercurePublisher::publishTyping(User $user, Conversation $conv)`
- [ ] JWT subscriber claims scoped per conversation topic
- [ ] `chat_controller.js`: EventSource → listen to topic, append message to DOM
- [ ] `typing_controller.js`: debounce input → POST `/typing`, listen SSE
- [ ] `PresenceService`: update `presence_status` + publish to Mercure
- [ ] Unread badge updated in real time
- [ ] EventSource cleanup on conversation switch (no orphan listeners)
- [ ] Tests: mocked Mercure publish, presence update

---

## Phase 4 — Async Email Notifications (Week 4)

**Goal: email sent if message unread after 5 minutes**

- [ ] `NotifyUnreadMessageMessage` (readonly DTO: `messageId`, `recipientId`)
- [ ] Dispatch in `MessageController::send()` with `DelayStamp(300_000)`
- [ ] `NotifyUnreadMessageHandler`:
    - Fetch `Message` by ID
    - Check `read_at === null`
    - Check `email_notifications_enabled === true`
    - Check `is_verified === true`
    - Send via `MailerInterface`
    - Log the send
- [ ] HTML + plain text email templates
- [ ] Messenger transport config (Doctrine) + routing in `messenger.yaml`
- [ ] Mailer DSN config (Brevo) in `.env.local`
- [ ] Test worker locally: `docker compose exec php bin/console messenger:consume async`
- [ ] Unit tests on handler (all 5 cases)

---

## Phase 5 — Profile & Admin (Week 5)

**Goal: user profile page, basic admin dashboard**

- [ ] `ProfileController`: edit username/email/avatar/password
- [ ] Avatar upload (manual approach: non-mapped transit property + mapped `avatarName`)
- [ ] `email_notifications_enabled` toggle
- [ ] `AdminController`: user list, enable/disable account
- [ ] `ROLE_ADMIN` protection + admin fixture
- [ ] Basic stats (messages today, active users)

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
