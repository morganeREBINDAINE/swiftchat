import { escapeHtml } from '../tools/escape-html.js';

export class ChatController {
    #messagesEl;
    #onReceiveMessage;
    #currentUser;
    #conversationId;
    #hub;
    #boundHandler;

    constructor(messagesEl, hub, onReceiveMessage) {
        this.#messagesEl      = messagesEl;
        this.#hub             = hub;
        this.#onReceiveMessage = onReceiveMessage;
        this.#currentUser     = messagesEl.dataset.currentUser;
        this.#conversationId  = messagesEl.dataset.conversationId;

        this.#boundHandler = (data) => this.#onMessage(data);
        hub.on('new_message', this.#boundHandler);
    }

    #onMessage(data) {
        if (data.conversationId !== this.#conversationId) return;
        if (data.senderUsername === this.#currentUser) return;

        this.#onReceiveMessage();
        this.#removeEmptyState();
        this.#appendBubble(data.content, data.senderUsername, data.createdAt, false);
    }

    appendOwn(content, createdAt) {
        this.#removeEmptyState();
        this.#appendBubble(content, this.#currentUser, createdAt, true);
    }

    #appendBubble(content, senderUsername, createdAt, isMine) {
        const time = new Date(createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const div = document.createElement('div');
        div.className = 'message ' + (isMine ? 'message--mine' : 'message--theirs');
        div.innerHTML =
            '<div class="message__bubble">' + escapeHtml(content).replace(/\n/g, '<br>') + '</div>' +
            '<span class="message__meta">' + time + (isMine ? ' ✓' : '') + '</span>';
        this.#messagesEl.appendChild(div);
        this.#messagesEl.scrollTop = this.#messagesEl.scrollHeight;
    }

    #removeEmptyState() {
        this.#messagesEl.querySelector('.empty-state')?.remove();
    }

    disconnect() {
        this.#hub.off('new_message', this.#boundHandler);
    }
}
