const OFFLINE_DELAY_MS = 3_000;

/**
 * Watches presence for one or more peers via a shared MercureHub.
 * Call watch(userId, callback) for each peer to track.
 * Offline updates are delayed by 3 s to absorb tab reloads and quick reconnects.
 */
export class PeerPresenceWatcher {
    #hub;
    #peers         = new Map(); // userId → (status: string) => void
    #offlineTimers = new Map(); // userId → timerId
    #boundHandler;

    constructor(hub) {
        this.#hub = hub;
        this.#boundHandler = (data) => this.#onPresence(data);
        hub.on('presence', this.#boundHandler);
    }

    watch(userId, onStatusChange) {
        this.#peers.set(userId, onStatusChange);
        return this;
    }

    #onPresence(data) {
        const callback = this.#peers.get(data.userId);
        if (!callback) return;

        if (data.status === 'offline') {
            clearTimeout(this.#offlineTimers.get(data.userId));
            const timer = setTimeout(() => {
                this.#offlineTimers.delete(data.userId);
                callback('offline');
            }, OFFLINE_DELAY_MS);
            this.#offlineTimers.set(data.userId, timer);
        } else {
            clearTimeout(this.#offlineTimers.get(data.userId));
            this.#offlineTimers.delete(data.userId);
            callback(data.status);
        }
    }

    disconnect() {
        this.#hub.off('presence', this.#boundHandler);
        this.#offlineTimers.forEach(t => clearTimeout(t));
        this.#offlineTimers.clear();
        this.#peers.clear();
    }
}
