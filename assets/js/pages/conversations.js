import { getMercureHub } from '../tools/mercure-hub.js';

(function () {
    const hubEl = document.getElementById('conversations-hub')
               || document.getElementById('chat-messages');
    if (!hubEl) return;

    const hub         = getMercureHub(hubEl.dataset.mercureUrl);
    const currentUser = hubEl.dataset.currentUser;
    const activeConvId = hubEl.dataset.conversationId || null;

    const handler = (data) => {
        if (!data.conversationId) return;
        if (data.conversationId === activeConvId) return;
        if (data.senderUsername === currentUser) return;

        // list page: per-conversation badge
        const badge = document.querySelector(`[data-conversation-id="${data.conversationId}"] .unread-badge`);
        if (badge) {
            const count = (parseInt(badge.textContent, 10) || 0) + 1;
            badge.textContent = count;
            badge.classList.remove('unread-badge--hidden');
        }

        // show page: aggregate badge on the back link
        const otherBadge = document.getElementById('other-unread-badge');
        if (otherBadge) {
            const count = (parseInt(otherBadge.textContent, 10) || 0) + 1;
            otherBadge.textContent = count;
            otherBadge.classList.remove('unread-badge--hidden');
        }
    };

    hub.on('new_message', handler);

    window.addEventListener('pagehide', () => {
        hub.off('new_message', handler);
        if (!document.getElementById('chat-messages')) {
            hub.close();
        }
    });
})();
