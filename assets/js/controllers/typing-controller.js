const TYPING_INTERVAL_MS = 2500;
const INDICATOR_TTL_MS   = 3000;

export class TypingController {
    #es         = null;
    #interval   = null;  // repeating tick while user is typing
    #lastKeyAt  = 0;     // timestamp of the most recent keypress
    #hideTimer  = null;
    #postUrl;
    #csrfToken;
    #currentUser;
    #indicator;

    /**
     * @param {HTMLElement} messagesEl  #chat-messages (carries data-* config)
     * @param {HTMLElement} textarea    message input
     * @param {HTMLElement} indicator   #typing-indicator display element
     */
    constructor(messagesEl, textarea, indicator) {
        this.#postUrl     = messagesEl.dataset.typingPostUrl;
        this.#csrfToken   = messagesEl.dataset.csrfTyping;
        this.#currentUser = messagesEl.dataset.currentUser;
        this.#indicator   = indicator;

        this.#connect(messagesEl.dataset.typingUrl);
        textarea.addEventListener('input', () => this.#onInput());
        window.addEventListener('pagehide', () => this.disconnect());
    }

    #connect(url) {
        this.#es = new EventSource(url, { withCredentials: true });
        this.#es.onmessage = (e) => this.#onMessage(e);
        this.#es.onerror   = () => {};
    }

    #onInput() {
        this.#lastKeyAt = Date.now();

        if (this.#interval !== null) return; // already ticking

        this.#postTyping(); // immediate on first key

        this.#interval = setInterval(() => {
            if (Date.now() - this.#lastKeyAt >= TYPING_INTERVAL_MS) {
                // No keypress in the last interval — user stopped typing
                clearInterval(this.#interval);
                this.#interval = null;
            } else {
                this.#postTyping();
            }
        }, TYPING_INTERVAL_MS);
    }

    async #postTyping() {
        const body = new FormData();
        body.set('_token', this.#csrfToken);
        try {
            await fetch(this.#postUrl, { method: 'POST', body });
        } catch {
            // best-effort — silent on network failure
        }
    }

    #onMessage(event) {
        let data;
        try {
            data = JSON.parse(event.data);
        } catch {
            return;
        }

        if (data.type !== 'typing') return;
        if (data.senderUsername === this.#currentUser) return;

        this.#show(data.senderUsername);
    }

    #show(username) {
        clearTimeout(this.#hideTimer);
        this.#indicator.textContent = `${username} is typing…`;
        this.#indicator.hidden = false;
        this.#hideTimer = setTimeout(() => this.hide(), INDICATOR_TTL_MS);
    }

    hide() {
        this.#indicator.hidden = true;
        this.#indicator.textContent = '';
    }

    disconnect() {
        clearInterval(this.#interval);
        clearTimeout(this.#hideTimer);
        this.#es?.close();
        this.#es = null;
    }
}
