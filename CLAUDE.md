# CLAUDE.md — SwiftChat

> Read this file in full before taking any action.

---

## Project

**SwiftChat** — real-time 1-to-1 messaging with deferred email notifications.
- Functional specs: `SPECS.md`
- Architecture & roadmap: `ROADMAP.md`

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Symfony 7.x (PHP 8.3+) |
| Runtime | FrankenPHP (via `dunglas/symfony-docker`) |
| Web server | Caddy (via `dunglas/symfony-docker`) |
| Real-time | Mercure Hub — **built into FrankenPHP, no separate service** |
| Async | Symfony Messenger + Doctrine transport |
| Mailer | Symfony Mailer + Brevo SMTP |
| Database | PostgreSQL |
| Frontend | Twig + Stimulus (or vanilla JS) |
| Tests | PHPUnit + Symfony WebTestCase |

---

## Docker Setup — Critical Context

This project is based on **`dunglas/symfony-docker`**. The full stack (FrankenPHP, Caddy, Mercure, PostgreSQL) runs via Docker Compose.

```bash
# Start the stack
docker compose up

# Run Symfony commands
docker compose exec php php bin/console <command>

# Install PHP packages
docker compose exec php composer require <package>

# Run tests
docker compose exec php bin/phpunit
```

### What NOT to do
- ❌ Do not install FrankenPHP or Caddy manually — managed by Docker
- ❌ Do not add a Mercure service in `docker-compose.yml` — it is built into FrankenPHP
- ❌ Do not modify `Caddyfile` or `frankenphp/` config unless strictly required
- ❌ Do not run `symfony serve` — use `docker compose up` instead

---

## Code Conventions — Absolute Rules

### PHP / Symfony
- PHP 8.3+: use **readonly properties**, **enums**, **named arguments**, **match expressions** where appropriate
- Strict typing everywhere: `declare(strict_types=1)` at the top of every file
- **Constructor injection only** — no `$this->getDoctrine()`, no service locator pattern
- **No business logic in controllers** — controllers call services, services do the work
- Doctrine entities: use **PHP attributes** (no YAML, no XML)
- UUIDs for all primary keys (`Uuid::v7()` via `symfony/uid`) — v7 is chronologically sortable
- Repository return types must always be explicit (`?Message`, `Message[]`, etc.)
- Naming:
  - Classes: `PascalCase`
  - Methods / properties: `camelCase`
  - Constants: `SCREAMING_SNAKE_CASE`
  - Route names: `snake_case` (e.g. `conversation_show`)

### Security — Non-negotiable
- **Symfony Voter** for every access to a user-owned resource (conversations, messages)
- Rate limiting on all write routes (Symfony RateLimiter component)
- CSRF protection on all forms and sensitive POST actions
- Never use `$_GET` / `$_POST` directly — always use the typed `Request` object
- Message content sanitization: `htmlspecialchars` at display time — **no HTML stored in messages**
- Mercure topics secured by JWT with strict subscriber claims (scoped to `conversation/{id}` per participant only)

### Messenger
- **Message DTOs** live in `src/Messenger/Message/` (readonly classes)
- **Handlers** live in `src/Messenger/Handler/` (decorated with `#[AsMessageHandler]`)
- Every Handler must have a dedicated unit test covering all cases
- `DelayStamp` for the email notification = **300 000 ms (5 minutes)**
- Never re-dispatch a message from inside a handler

### Mercure
- All Mercure publications go through the **`MercurePublisher` service** — never publish directly from a controller
- Topics:
  - `conversation/{conversationId}` — new messages
  - `typing/{conversationId}` — typing indicator
  - `presence/{userId}` — online/offline status
- Published payloads are serialized as JSON

### Database
- **Always create a migration** after modifying an entity (`doctrine:migrations:diff`)
- After generating a migration with `doctrine:migrations:diff`, STOP and show me the file
- Never edit an already-applied migration — create a new one
- Never run `doctrine:migrations:migrate` without explicit confirmation
- Let me review the migration SQL before applying it
- Fixtures in `src/DataFixtures/` — include at least 2 verified users with sample messages for portfolio demo
- Never use `findAll()` without a limit — always paginate or constrain queries

### Frontend
- No jQuery
- EventSource (Mercure) interactions are encapsulated in a Stimulus controller or a dedicated JS class
- **Always close EventSource listeners** when the user navigates away from a conversation — no orphan listeners
- `Enter` sends the message, `Shift+Enter` inserts a line break

---

## Directory Map — What Goes Where

```
src/
  Controller/       # Thin — delegate to services
  Entity/           # Doctrine ORM, PHP attributes
  Repository/       # DQL / QueryBuilder queries
  Form/             # Symfony FormTypes
  Messenger/
    Message/        # DTOs (readonly classes)
    Handler/        # Handlers
  Security/
    Voter/          # One voter per resource type
  Service/          # Business logic
templates/
  auth/
  conversation/
  profile/
  emails/           # HTML + plain text email templates
  base.html.twig
assets/
  controllers/      # Stimulus JS controllers
  styles/
tests/
  Unit/             # Pure PHPUnit (handlers, services)
  Functional/       # WebTestCase (controllers, auth, access control)
```

---

## Environment Variables

```dotenv
# .env.local — never commit this file
DATABASE_URL="postgresql://app:!ChangeMe!@database:5432/app"
MAILER_DSN="smtp://user:pass@smtp-relay.brevo.com:587"
MERCURE_URL="https://localhost/.well-known/mercure"
MERCURE_PUBLIC_URL="https://localhost/.well-known/mercure"
MERCURE_JWT_SECRET="your-mercure-jwt-secret"
APP_SECRET="your-app-secret"
```

> Note: `dunglas/symfony-docker` sets `DATABASE_URL` automatically in dev via `docker-compose.override.yml`. Only override if needed.

---

## Key Business Logic — Step by Step

### Sending a message
1. Controller validates request + checks voter (`ConversationVoter::SEND`)
2. `ConversationService::findOrCreate(User $a, User $b)` returns the conversation
3. Create and persist the `Message`
4. `MercurePublisher::publishMessage($message)` publishes to the hub
5. Dispatch `NotifyUnreadMessageMessage` with `new DelayStamp(300_000)`
6. Return JSON `{id, content, createdAt, sender}` for DOM update

### Marking a message as read
- Route: `PATCH /message/{id}/read`
- Verify that the current user is the **recipient** (not the sender)
- Set `read_at = now()` only if `read_at === null`
- Publish a Mercure update to notify the sender ("read" status)

### NotifyUnreadMessageHandler — decision tree
```
1. Fetch Message by ID
2. Message not found? → log warning, return
3. Message.readAt !== null? → return (already read)
4. recipient.emailNotificationsEnabled === false? → return
5. recipient.isVerified === false? → return
6. Send email via MailerInterface
7. Log the send
```

---

## Test Coverage Requirements

### Functional (WebTestCase)
- Registration: success, duplicate email, weak password
- Login: success, wrong password, unverified account
- Send message: success, access to someone else's conversation (403)
- Mark as read: success, attempt by the sender (403)

### Unit (PHPUnit)
- `NotifyUnreadMessageHandler`: all 5 cases (message read, not found, notifications off, email unverified, successful send)
- `ConversationService::findOrCreate()`: existing conversation, new conversation
- `ConversationVoter`: participant allowed, third party denied

---

## Watch-outs

- **Mercure JWT in dev**: `MERCURE_JWT_SECRET` must match between the FrankenPHP config and the bundle config — check `docker-compose.override.yml` and `config/packages/mercure.yaml`
- **DelayStamp**: only works with Doctrine transport (not `sync`) — verify routing in `config/packages/messenger.yaml` before testing
- **UUID v7**: requires `symfony/uid` — use `Uuid::v7()`, not `Uuid::v4()`
- **symfonycasts/verify-email-bundle**: use it for email verification — do not reimplement
- **remember_me**: configure the secret in `security.yaml`
- **Fixtures**: must include at least 2 verified users + sample messages for portfolio demo
- After completing any feature, verify there are no php-lsp errors/warnings before marking the task done

---

## What Claude Code Must NOT Do

- Put business logic in controllers
- Use `findAll()` without pagination
- Edit already-applied migrations
- Access conversations without going through the Voter
- Send emails directly from a controller (use Messenger)
- Commit `.env.local` or any secrets
- Add CSRF-free or rate-limit-free write endpoints
- Use `$_GET` / `$_POST` directly
- Add a Mercure service in `docker-compose.yml`
- Run `symfony serve` — use `docker compose up`
- Leave EventSource listeners open after a conversation switch

---

## Git
- Commit after each completed task with conventional commits format
- Format: type(scope): description  (feat, fix, chore, refactor...)
- Example: feat(auth): add registration form with email verification
- Keep commit messages short: max 72 characters for the subject line
- Commit subject only (no body) — the code speaks for itself
