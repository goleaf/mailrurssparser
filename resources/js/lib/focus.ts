const FOCUSABLE_SELECTOR = [
    'a[href]',
    'area[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

export function getActiveHtmlElement(): HTMLElement | null {
    if (typeof document === 'undefined') {
        return null;
    }

    return document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
}

export function getFocusableElements(container: HTMLElement | null): HTMLElement[] {
    if (container === null || typeof window === 'undefined') {
        return [];
    }

    return Array.from(
        container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR),
    ).filter((element) => {
        if (element.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        const style = window.getComputedStyle(element);

        return style.display !== 'none' && style.visibility !== 'hidden';
    });
}

export function trapFocusWithin(
    event: KeyboardEvent,
    container: HTMLElement | null,
): void {
    if (event.key !== 'Tab') {
        return;
    }

    const focusableElements = getFocusableElements(container);

    if (focusableElements.length === 0) {
        event.preventDefault();
        container?.focus();

        return;
    }

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];
    const activeElement = getActiveHtmlElement();

    if (event.shiftKey) {
        if (activeElement === firstElement || activeElement === container) {
            event.preventDefault();
            lastElement.focus();
        }

        return;
    }

    if (activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
    }
}
