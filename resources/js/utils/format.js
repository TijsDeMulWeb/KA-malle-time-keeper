export function padTwo(value) {
    return String(value).padStart(2, '0');
}

export function formatClock(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${hours}:${padTwo(minutes)}:${padTwo(seconds % 60)}`;
}

export function formatTime(totalMinutes) {
    return `${padTwo(Math.floor(totalMinutes / 60))}:${padTwo(totalMinutes % 60)}`;
}
