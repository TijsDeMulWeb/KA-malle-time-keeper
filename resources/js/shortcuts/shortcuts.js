const typingTags = ['INPUT', 'TEXTAREA', 'SELECT'];

export function isTyping(element) {
    if (!element) {
        return false;
    }

    return typingTags.includes(element.tagName) || element.isContentEditable;
}

export function findShortcutTarget(key, root = document) {
    return root.querySelector(`[data-shortcut="${CSS.escape(key)}"]`);
}

export function handleKeydown(event, root = document) {
    if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
        return;
    }

    if (isTyping(event.target) || root.querySelector('dialog[open]')) {
        return;
    }

    const target = findShortcutTarget(event.key, root);

    if (!target) {
        return;
    }

    event.preventDefault();
    target.click();
}

export function initShortcuts() {
    document.addEventListener('keydown', (event) => handleKeydown(event));
}
