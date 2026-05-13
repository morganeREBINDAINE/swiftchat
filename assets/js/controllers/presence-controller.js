const HEARTBEAT_INTERVAL_MS = 60_000;   // 1 minute
const IDLE_THRESHOLD_MS     = 120_000;  // 2 minutes with no interaction → away
const OFFLINE_DELAY_MS      = 3_000;   // delay before showing a peer as offline

export class PresenceController {
    #heartbeatInterval = null;
    #lastActivityAt    = Date.now();
    #offlineTimers     = new Map(); // userId → setTimeout id

    #heartbeatUrl;
    #offlineUrl;
    #csrfHeartbeat;
    #csrfOffline;

    constructor({ heartbeatUrl, offlineUrl, csrfHeartbeat, csrfOffline }) {
        this.#heartbeatUrl  = heartbeatUrl;
        this.#offlineUrl    = offlineUrl;
        this.#csrfHeartbeat = csrfHeartbeat;
        this.#csrfOffline   = csrfOffline;

        this.#trackActivity();
        this.#sendHeartbeat();
        this.#heartbeatInterval = setInterval(() => this.#sendHeartbeat(), HEARTBEAT_INTERVAL_MS);

        document.addEventListener('visibilitychange', () => this.#sendHeartbeat());
        window.addEventListener('beforeunload', () => this.#sendOfflineBeacon());
    }

    /**
     * Call this when a Mercure presence event arrives for a peer.
     * Delays 'offline' updates by OFFLINE_DELAY_MS to absorb quick reconnects.
     *
     * @param {string}   userId
     * @param {string}   status    'online' | 'away' | 'offline'
     * @param {Function} callback  (userId, status) => void
     */
    handlePresenceEvent(userId, status, callback) {
        if (status === 'offline') {
            const timer = setTimeout(() => {
                this.#offlineTimers.delete(userId);
                callback(userId, 'offline');
            }, OFFLINE_DELAY_MS);
            this.#offlineTimers.set(userId, timer);
        } else {
            clearTimeout(this.#offlineTimers.get(userId));
            this.#offlineTimers.delete(userId);
            callback(userId, status);
        }
    }

    #getStatus() {
        if (document.hidden) return 'away';
        if (Date.now() - this.#lastActivityAt > IDLE_THRESHOLD_MS) return 'away';
        return 'online';
    }

    #trackActivity() {
        const refresh = () => { this.#lastActivityAt = Date.now(); };
        ['mousemove', 'keydown', 'scroll', 'click'].forEach(ev =>
            document.addEventListener(ev, refresh, { passive: true })
        );
    }

    async #sendHeartbeat() {
        const body = new FormData();
        body.set('status', this.#getStatus());
        body.set('_token', this.#csrfHeartbeat);
        try {
            await fetch(this.#heartbeatUrl, { method: 'POST', body });
        } catch {
            // best-effort — silent on network error
        }
    }

    #sendOfflineBeacon() {
        clearInterval(this.#heartbeatInterval);
        const body = new FormData();
        body.set('_token', this.#csrfOffline);
        navigator.sendBeacon(this.#offlineUrl, body);
    }
}
