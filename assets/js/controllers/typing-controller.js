const TYPING_INTERVAL_MS = 2000;
const INDICATOR_TTL_MS = 2000;

export class TypingController {
    #interval = null;
    #lastKeyAt = 0;
    #hideTimer = null;
    #postUrl;
    #csrfToken;
    #currentUser;
    #indicator;
    #boundHandler;
    #hub;

    constructor(messagesEl, hub, textarea, indicator) {
        this.#postUrl = messagesEl.dataset.typingPostUrl;
        this.#csrfToken = messagesEl.dataset.csrfTyping;
        this.#currentUser = messagesEl.dataset.currentUser;
        this.#indicator = indicator;
        this.#hub = hub;

        this.#boundHandler = (data) => this.#onMessage(data);
        hub.on('typing', this.#boundHandler);

        textarea.addEventListener('input', () => this.#onInput());
    }

    #onInput() {
        this.#lastKeyAt = Date.now();

        if (this.#interval !== null) return;

        this.#postTyping();

        this.#interval = setInterval(() => {
            const diff = Date.now() - this.#lastKeyAt;
            if (diff >= 500) {
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
            await fetch(this.#postUrl, {method: 'POST', body});
        } catch {
            // best-effort — silent on network failure
        }
    }

    #onMessage(data) {
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
        this.#hub.off('typing', this.#boundHandler);
    }
}
