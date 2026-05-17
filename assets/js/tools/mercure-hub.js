let instance = null;

class MercureHub {
    #es = null;
    #handlers = new Map();

    constructor(url) {
        this.#es = new EventSource(url, { withCredentials: true });
        this.#es.onmessage = (e) => this.#dispatch(e);
        this.#es.onerror = () => {};
    }

    on(type, callback) {
        if (!this.#handlers.has(type)) this.#handlers.set(type, new Set());
        this.#handlers.get(type).add(callback);
        return this;
    }

    off(type, callback) {
        this.#handlers.get(type)?.delete(callback);
        return this;
    }

    #dispatch(event) {
        let data;
        try { data = JSON.parse(event.data); } catch { return; }
        if (!data.type) return;
        this.#handlers.get(data.type)?.forEach(cb => cb(data));
    }

    close() {
        this.#es?.close();
        this.#es = null;
        this.#handlers.clear();
        instance = null;
    }
}

export function getMercureHub(url) {
    if (!instance) {
        if (!url) throw new Error('MercureHub: URL required for first initialization');
        instance = new MercureHub(url);
    }
    return instance;
}
