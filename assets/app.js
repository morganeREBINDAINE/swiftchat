import './styles/app.css';
import { PresenceController } from './js/controllers/presence-controller.js';

const b = document.body;
if (b.dataset.presenceHeartbeatUrl) {
    new PresenceController({
        heartbeatUrl:  b.dataset.presenceHeartbeatUrl,
        offlineUrl:    b.dataset.presenceOfflineUrl,
        csrfHeartbeat: b.dataset.presenceCsrfHeartbeat,
        csrfOffline:   b.dataset.presenceCsrfOffline,
    });
}
