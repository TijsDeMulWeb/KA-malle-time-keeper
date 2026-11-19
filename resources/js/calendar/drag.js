import { formatTime } from '../utils/format.js';
import { createRange, edgeAt, minutesToPixels, moveRange, pixelsToMinutes, resizeRange } from './geometry.js';

const movementThreshold = 4;

let lastDragged = null;

function root() {
    return document.querySelector('[data-calendar]');
}

function pixelsPerHour() {
    return Number(root().dataset.pixelsPerHour);
}

function columnAt(x, y) {
    return document.elementsFromPoint(x, y).find((element) => element.dataset?.calendarDay !== undefined) ?? null;
}

function suppressNextClick() {
    const stop = (event) => {
        event.stopPropagation();
        event.preventDefault();
    };

    window.addEventListener('click', stop, { capture: true, once: true });
    setTimeout(() => window.removeEventListener('click', stop, { capture: true }), 100);
}

function dispatchToLivewire(name, params) {
    window.Livewire?.dispatch(name, params);
}

function distance(event, origin) {
    return Math.hypot(event.clientX - origin.x, event.clientY - origin.y);
}

function startBlockDrag(event, block) {
    if (event.button !== 0 || block.dataset.draggable !== 'true') {
        return;
    }

    const perHour = pixelsPerHour();
    const column = block.closest('[data-calendar-day]');
    const mode = edgeAt(event.clientY - block.getBoundingClientRect().top, block.getBoundingClientRect().height);
    const origin = { x: event.clientX, y: event.clientY };
    const start = Number(block.dataset.startMinutes);
    const end = Number(block.dataset.endMinutes);
    const original = { top: block.style.top, height: block.style.height, transform: block.style.transform };

    let range = { start, end };
    let date = column.dataset.calendarDay;
    let moved = false;

    block.setPointerCapture(event.pointerId);

    const onMove = (moveEvent) => {
        if (!moved && distance(moveEvent, origin) < movementThreshold) {
            return;
        }

        moved = true;

        const delta = pixelsToMinutes(moveEvent.clientY - origin.y, perHour);

        range = mode === 'move' ? moveRange(start, end, delta) : resizeRange(mode, start, end, delta);

        block.style.top = `${minutesToPixels(range.start, perHour)}px`;
        block.style.height = `${minutesToPixels(range.end - range.start, perHour)}px`;
        block.style.zIndex = '30';

        if (mode !== 'move') {
            return;
        }

        const target = columnAt(moveEvent.clientX, moveEvent.clientY);

        if (target) {
            date = target.dataset.calendarDay;
            block.style.transform = `translateX(${target.getBoundingClientRect().left - column.getBoundingClientRect().left}px)`;
        }
    };

    const onUp = () => {
        block.removeEventListener('pointermove', onMove);
        block.removeEventListener('pointerup', onUp);
        block.removeEventListener('pointercancel', onUp);

        if (!moved) {
            return;
        }

        suppressNextClick();

        lastDragged = { block, original };

        dispatchToLivewire('entry-moved', {
            entry: Number(block.dataset.entryId),
            date,
            start: formatTime(range.start),
            end: formatTime(range.end),
        });
    };

    block.addEventListener('pointermove', onMove);
    block.addEventListener('pointerup', onUp);
    block.addEventListener('pointercancel', onUp);
}

function startSlotDrag(event, slot) {
    if (event.button !== 0 || event.pointerType === 'touch') {
        return;
    }

    const perHour = pixelsPerHour();
    const column = slot.closest('[data-calendar-day]');
    const origin = { x: event.clientX, y: event.clientY };
    const anchor = pixelsToMinutes(event.clientY - column.getBoundingClientRect().top, perHour);

    let range = null;
    let ghost = null;

    slot.setPointerCapture(event.pointerId);

    const onMove = (moveEvent) => {
        if (!ghost && distance(moveEvent, origin) < movementThreshold) {
            return;
        }

        const current = pixelsToMinutes(moveEvent.clientY - column.getBoundingClientRect().top, perHour);

        range = createRange(anchor, current);

        if (!ghost) {
            ghost = document.createElement('div');
            ghost.className = 'pointer-events-none absolute inset-x-0.5 z-20 rounded-md border border-sky-500 bg-sky-500/25';
            column.appendChild(ghost);
        }

        ghost.style.top = `${minutesToPixels(range.start, perHour)}px`;
        ghost.style.height = `${minutesToPixels(range.end - range.start, perHour)}px`;
    };

    const onUp = () => {
        slot.removeEventListener('pointermove', onMove);
        slot.removeEventListener('pointerup', onUp);
        slot.removeEventListener('pointercancel', onUp);

        if (!ghost) {
            return;
        }

        ghost.remove();
        suppressNextClick();

        dispatchToLivewire('open-entry', {
            date: column.dataset.calendarDay,
            start: formatTime(range.start),
            end: formatTime(range.end),
        });
    };

    slot.addEventListener('pointermove', onMove);
    slot.addEventListener('pointerup', onUp);
    slot.addEventListener('pointercancel', onUp);
}

function showResizeCursor(event) {
    const block = event.target.closest?.('[data-entry-id][data-draggable="true"]');

    if (!block) {
        return;
    }

    const rect = block.getBoundingClientRect();
    const edge = edgeAt(event.clientY - rect.top, rect.height);

    block.style.cursor = edge === 'move' ? 'grab' : 'ns-resize';
}

function restoreRejectedMove() {
    if (!lastDragged) {
        return;
    }

    Object.assign(lastDragged.block.style, lastDragged.original);

    lastDragged = null;
}

export function initCalendarDrag() {
    document.addEventListener('pointerdown', (event) => {
        if (!root()) {
            return;
        }

        const block = event.target.closest('[data-entry-id]');

        if (block) {
            startBlockDrag(event, block);

            return;
        }

        const slot = event.target.closest('[data-calendar-slot]');

        if (slot) {
            startSlotDrag(event, slot);
        }
    });

    document.addEventListener('pointermove', showResizeCursor);
    document.addEventListener('entry-move-rejected', restoreRejectedMove);
    document.addEventListener('entries-changed', () => {
        lastDragged = null;
    });
}
