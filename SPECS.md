# SPECS.md — SwiftChat
## Real-time messaging system with deferred email notifications

---

## 1. Overview

SwiftChat is a real-time messaging application allowing registered users to exchange messages instantly via SSE (Server-Sent Events) through Mercure. If a message has not been read within 5 minutes of receipt, an email notification is sent to the recipient via Symfony Messenger (async processing).

**Target stack:**
- Symfony 7.x (PHP 8.3+)
- Mercure (built-in via `dunglas/symfony-docker` — FrankenPHP + Caddy)
- Symfony Messenger + Doctrine transport
- Symfony Mailer + Brevo (SMTP)
- PostgreSQL
- Twig (SSR frontend + Stimulus or vanilla JS)

---

## 2. Authentication & User Management

### 2.1 Registration
- Registration form fields: `username`, `email`, `password` (confirmed), `avatar` (optional)
- Validation rules:
    - `username`: unique, 3–30 alphanumeric characters + hyphens
    - `email`: unique, valid format
    - `password`: minimum 8 characters, at least 1 digit and 1 uppercase letter
- Password hashing via `PasswordHasherInterface`
- Confirmation email sent after registration (verification link, expires in 24h)
- Account inactive until email is confirmed

### 2.2 Login / Logout
- Standard Symfony Security login form (`form_login`)
- "Remember me" cookie support
- Post-login redirect to the messaging interface
- Logout with session invalidation

### 2.3 User Profile
- Profile page: edit `username`, `email`, `avatar`, `password`
- Presence status: `online` / `offline` / `away` (updated automatically)
- Notification preferences: enable/disable email notifications

### 2.4 Roles
- `ROLE_USER`: standard user
- `ROLE_ADMIN`: access to a basic supervision dashboard (user list, stats)

---

## 3. Real-time Messaging

### 3.1 Conversations
- One conversation = two users (1-to-1 only, no group chat in v1)
- Conversation created automatically on first message between two users
- Conversation list sorted by latest message (most recent first)
- Unread message indicator per conversation (numeric badge)
- User search to start a new conversation

### 3.2 Messages
- Fields: `id`, `conversation_id`, `sender_id`, `content`, `created_at`, `read_at` (nullable)
- Maximum content length: 2000 characters
- Message marked as `read_at = now()` as soon as the recipient **opens the conversation** and the message is visible in the viewport
- Display status: "sent" / "read" (with timestamp)

### 3.3 Real-time with Mercure
- On message send: publish to topic `conversation/{id}` via the Mercure Hub
- Recipient's browser subscribes to their conversation topics via EventSource
- UI updates without page reload (message appended, badge updated)
- Typing indicator ("X is typing…") — Mercure publication on dedicated topic `typing/{conversation_id}`
- Presence status updated in real time

### 3.4 Message Loading
- Cursor-based pagination (50 messages per page, infinite scroll upward)
- Auto-scroll to bottom when opening a conversation

---

## 4. Email Notifications (Messenger + Worker)

### 4.1 Trigger Logic
- On each message sent, a `NotifyUnreadMessageMessage` Messenger message is dispatched with a **5-minute delay** (`DelayStamp`)
- When processed by the worker:
    - If `read_at` is still `null` → send email
    - If `read_at` is set → skip (already read)

### 4.2 Email Content
- Subject: `[SwiftChat] You have a new message from {senderUsername}`
- Body: sender's username, message excerpt (first 50 characters + "…"), direct link to the conversation
- Dedicated Twig template (`emails/unread_notification.html.twig`)
- Plain text version included

### 4.3 Constraints
- One email per unread message maximum (no spam if multiple unread messages)
- Can be disabled per user (profile preference)
- Check that the recipient has confirmed their email before sending

### 4.4 Worker
- Command: `messenger:consume async --limit=100`
- Supervised in production (Supervisor or FrankenPHP worker mode)
- Retry: 3 attempts max, exponential backoff, then `FailedMessagesTransport`

---

## 5. User Interface

### 5.1 Global Layout
- Responsive design (mobile-first)
- Left sidebar: conversation list + user search bar
- Center area: active conversation window
- Header: logo, logged-in username, profile link, logout

### 5.2 Visual States
- Messages sent by me: right-aligned, colored background
- Received messages: left-aligned, neutral background
- "Read" indicator below message (icon + timestamp)
- Unread badge on sidebar
- Animated typing indicator

### 5.3 UX
- Send message with `Enter` (Shift+Enter for line break)
- Auto-scroll to bottom on new message receipt if user is already at the bottom
- Optional sound notification on receipt (client-side only)
- No JS framework (Stimulus or vanilla JS for Mercure interactions)

---

## 6. Security

- CSRF protection on all forms
- Authentication required on all messaging routes (`IS_AUTHENTICATED_FULLY`)
- Users can only access their own conversations (Symfony Voter)
- Rate limiting on message sending (max 30 messages/minute per user)
- Message content sanitization (`htmlspecialchars` on display — no HTML in messages)
- Mercure topics secured by JWT (subscriber claims)

---

## 7. Administration (optional v1)

- `/admin` route protected by `ROLE_ADMIN`
- User list (active/inactive, registration date)
- Stats: message count today, active users
- Ability to disable a user account

---

## 8. Out of Scope (v1)

- Group messaging
- Voice messages / file attachments
- Emoji reactions on messages
- Video/audio calls
- Native mobile app
- End-to-end encryption

---

## 9. Quality Criteria

- Functional tests (WebTestCase) on critical routes: registration, login, message send
- Unit tests on Messenger handlers
- Target minimum coverage: 60%
- Complete README with local setup instructions
