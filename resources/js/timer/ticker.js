import { formatClock } from '../utils/format.js';

const selector = '[data-timer-started-at]';

export function updateTimers(now = Date.now()) {
    document.querySelectorAll(selector).forEach((element) => {
        const startedAt = Date.parse(element.dataset.timerStartedAt);

        if (Number.isNaN(startedAt)) {
            return;
        }

        element.textContent = formatClock((now - startedAt) / 1000);
    });
}

export function initTicker() {
    updateTimers();

    setInterval(updateTimers, 1000);
}
