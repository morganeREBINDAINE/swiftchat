import { escapeHtml } from '../tools/escape-html.js';

export class ChatController {
    #es = null;
    #messagesEl;
    #onReceiveMessage;
    #currentUser;

    constructor(messagesEl, onReceiveMessage) {
        this.#messagesEl  = messagesEl;
        this.#onReceiveMessage  = onReceiveMessage;
        this.#currentUser = messagesEl.dataset.currentUser;
        this.#connect(messagesEl.dataset.mercureUrl);
        window.addEventListener('pagehide', () => this.disconnect());
    }

    #connect(url) {
        this.#es = new EventSource(url, { withCredentials: true });
        this.#es.onmessage = (e) => this.#onMessage(e);
        this.#es.onerror = () => {};
    }

    #onMessage(event) {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch {
            return;
        }

        if (data.type !== 'new_message') return;
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
        this.#es?.close();
        this.#es = null;
    }
}
