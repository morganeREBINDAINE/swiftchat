# ROADMAP.md — SwiftChat
## Technical architecture & development plan

> This document is a living reference. Phases and priorities may be reordered as the project evolves.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT (Browser)                      │
│  Twig + Stimulus/Vanilla JS + EventSource (Mercure SSE)     │
└──────────────┬──────────────────────────┬───────────────────┘
               │ HTTP/HTTPS               │ SSE (Mercure)
               ▼                          ▼
┌──────────────────────────────────────────────────────────────┐
│          dunglas/symfony-docker container stack               │
│                                                              │
│  ┌────────────────────┐     ┌──────────────────────────┐    │
│  │  FrankenPHP + Caddy │◄───►│    Mercure Hub            │    │
│  │  (Symfony 7 app)    │     │  (built-in, no extra     │    │
│  │                    │     │   service needed)         │    │
│  │  Controllers       │     └──────────────────────────┘    │
│  │  Repositories      │                                      │
│  │  Services          │     ┌──────────────────────────┐    │
│  │  Voters            │────►│   Messenger Worker        │    │
│  │  Twig Templates    │     │  (Doctrine transport)    │    │
│  └────────┬───────────┘     └──────────────┬───────────┘    │
│           │                                │                  │
│           ▼                                │ Symfony Mailer   │
│  ┌─────────────────────┐                  ▼                  │
│  │     PostgreSQL       │    ┌──────────────────────────┐    │
│  │  users              │    │       Brevo SMTP           │    │
│  │  conversations      │    │  (email notifications)    │    │
│  │  messages           │    └──────────────────────────┘    │
│  │  messenger_messages │                                      │
│  └─────────────────────┘                                      │
└──────────────────────────────────────────────────────────────┘
```

---

## Project Bootstrap

This project uses **[`dunglas/symfony-docker`](https://github.com/dunglas/symfony-docker)** as its base, which provides a production-ready Docker stack out of the box.

### What this stack gives us for free
- **FrankenPHP** as the PHP runtime (replaces nginx + php-fpm)
- **Caddy** as the web server with automatic SSL
- **Mercure Hub built-in** — no separate Mercure service needed; it runs inside FrankenPHP
- **PostgreSQL** service pre-configured
- Hot reload in dev, optimized builds for prod

### Initial setup (already done)
```bash
git clone https://github.com/dunglas/symfony-docker.git swiftchat
cd swiftchat
docker compose up --build   # bootstraps the full stack + creates Symfony app
```

### Important: do NOT
- Install FrankenPHP or Caddy manually — they are managed by the Docker stack
- Add a separate Mercure service in `docker-compose.yml` — it is already built into FrankenPHP
- Modify `Caddyfile` or `frankenphp/` config unless strictly necessary

### Daily dev workflow
```bash
docker compose up           # start the stack
docker compose exec php bin/console ...   # run Symfony commands
docker compose exec php composer require ...
docker compose down         # stop
```

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

## Directory Structure

```
src/
├── Controller/
│   ├── AuthController.php          # registration, email verification
│   ├── ConversationController.php  # list, new conversation
│   ├── MessageController.php       # send, mark-as-read
│   ├── ProfileController.php       # profile editing
│   └── AdminController.php         # admin dashboard
├── Entity/
│   ├── User.php
│   ├── Conversation.php
│   ├── Message.php
│   └── EmailVerificationToken.php
├── Repository/
│   ├── UserRepository.php
│   ├── ConversationRepository.php
│   └── MessageRepository.php
├── Form/
│   ├── RegistrationFormType.php
│   └── ProfileFormType.php
├── Messenger/
│   ├── Message/
│   │   └── NotifyUnreadMessageMessage.php
│   └── Handler/
│       └── NotifyUnreadMessageHandler.php
├── Security/
│   ├── Voter/
│   │   └── ConversationVoter.php
│   └── EmailVerifier.php
├── Service/
│   ├── MercurePublisher.php
│   ├── ConversationService.php
│   └── PresenceService.php
└── Twig/Extension/
    └── TimeAgoExtension.php
templates/
├── auth/
│   ├── register.html.twig
│   └── login.html.twig
├── conversation/
│   ├── index.html.twig
│   ├── _list.html.twig
│   └── _messages.html.twig
├── profile/
│   └── edit.html.twig
├── emails/
│   ├── unread_notification.html.twig
│   └── unread_notification.txt.twig
└── base.html.twig
assets/
├── controllers/
│   ├── chat_controller.js          # EventSource + DOM updates
│   └── typing_controller.js        # typing indicator
└── styles/
    └── app.css
tests/
├── Unit/
└── Functional/
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
- [ ] `MessageController::markAsRead()` (PATCH `read_at`)
- [ ] `ConversationVoter`: only participants can access a conversation
- [ ] Cursor pagination in `MessageRepository::findBeforeId()`
- [ ] Twig templates: messaging layout, sidebar, message window
- [ ] Rate limiting on send (RateLimiter component)
- [ ] Tests: send message, access denied, mark as read

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

## Technical Decisions

| Topic         | Decision           |
|---------------|--------------------|
| JS frontend   | Stimulus           |
| Avatar upload | VichUploaderBundle |
| CSS           | Bootstrap 5        |

---

## Known Risks

- **Mercure JWT in dev**: the built-in hub requires correct `MERCURE_JWT_SECRET` env — double-check `docker-compose.override.yml`
- **DelayStamp**: requires Doctrine transport (not `sync`); verify `messenger.yaml` routing before testing
- **EventSource cleanup**: JS listeners must be closed on conversation switch to avoid memory leaks
- **UUID v7**: chronologically sortable — prefer over v4 for messages (allows `ORDER BY id`)
