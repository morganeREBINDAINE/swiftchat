import { getMercureHub }       from '../tools/mercure-hub.js';
import { ChatController }       from '../controllers/chat-controller.js';
import { TypingController }     from '../controllers/typing-controller.js';
import { PeerPresenceWatcher }  from '../controllers/peer-presence-watcher.js';

(function () {
    const messagesEl = document.getElementById('chat-messages');
    if (!messagesEl) return;

    const textarea  = document.getElementById('message-input');
    const form      = document.getElementById('send-form');
    const sendBtn   = document.getElementById('send-btn');
    const indicator = document.getElementById('typing-indicator');

    const hub    = getMercureHub(messagesEl.dataset.mercureUrl);
    const typing = new TypingController(messagesEl, hub, textarea, indicator);
    const chat   = new ChatController(messagesEl, hub, () => typing.hide());

    let watcher = null;
    const otherId = messagesEl.dataset.otherId;
    if (otherId) {
        watcher = new PeerPresenceWatcher(hub).watch(otherId, (status) => {
            const dot   = document.getElementById('presence-dot');
            const label = document.getElementById('presence-label');
            if (dot)   dot.className = `presence-dot presence-dot--${status}`;
            if (label) label.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        });
    }

    window.addEventListener('pagehide', () => {
        watcher?.disconnect();
        hub.close();
    });

    messagesEl.scrollTop = messagesEl.scrollHeight;

    textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    textarea.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const content = textarea.value.trim();
        if (!content) return;

        sendBtn.disabled = true;

        let data;
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
            data = await res.json();

            if (!res.ok) {
                alert(data.error || 'Failed to send message.');
                return;
            }
        } catch {
            alert('Network error. Please try again.');
            return;
        } finally {
            sendBtn.disabled = false;
        }

        chat.appendOwn(data.content, data.createdAt);

        textarea.value = '';
        textarea.style.height = 'auto';
    });
})();
