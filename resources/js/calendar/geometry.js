export const snapStep = 15;
export const minutesPerDay = 1440;

export function snap(minutes, step = snapStep) {
    return Math.round(minutes / step) * step;
}

export function pixelsToMinutes(pixels, pixelsPerHour) {
    return (pixels / pixelsPerHour) * 60;
}

export function minutesToPixels(minutes, pixelsPerHour) {
    return (minutes / 60) * pixelsPerHour;
}

export function moveRange(start, end, deltaMinutes) {
    const duration = end - start;
    const newStart = Math.min(Math.max(snap(start + deltaMinutes), 0), minutesPerDay - duration);

    return { start: newStart, end: newStart + duration };
}

export function resizeRange(edge, start, end, deltaMinutes, minimum = snapStep) {
    if (edge === 'start') {
        const newStart = Math.min(Math.max(snap(start + deltaMinutes), 0), end - minimum);

        return { start: newStart, end };
    }

    const newEnd = Math.max(Math.min(snap(end + deltaMinutes), minutesPerDay), start + minimum);

    return { start, end: newEnd };
}

export function createRange(anchorMinutes, currentMinutes, minimum = 15) {
    const first = Math.min(anchorMinutes, currentMinutes);
    const last = Math.max(anchorMinutes, currentMinutes);
    const start = Math.min(Math.max(snap(first), 0), minutesPerDay - minimum);
    const end = Math.min(Math.max(snap(last), start + minimum), minutesPerDay);

    return { start, end };
}

export function edgeAt(offsetY, height, edgeSize = 7) {
    if (height < edgeSize * 3) {
        return 'move';
    }

    if (offsetY <= edgeSize) {
        return 'start';
    }

    if (offsetY >= height - edgeSize) {
        return 'end';
    }

    return 'move';
}
