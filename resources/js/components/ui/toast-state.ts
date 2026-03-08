export type ToastType = 'success' | 'error' | 'info' | 'warning';

export type ToastItem = {
    id: number;
    message: string;
    type: ToastType;
};

let nextToastId = 1;
let toastItems: ToastItem[] = [];
const listeners = new Set<(items: ToastItem[]) => void>();

function notify(): void {
    const snapshot = [...toastItems];

    for (const listener of listeners) {
        listener(snapshot);
    }
}

export function dismissToast(id: number): void {
    toastItems = toastItems.filter((toast) => toast.id !== id);
    notify();
}

export function showToast(message: string, type: ToastType = 'info'): void {
    const id = nextToastId++;

    toastItems = [...toastItems, { id, message, type }];
    notify();

    if (typeof window !== 'undefined') {
        window.setTimeout(() => {
            dismissToast(id);
        }, 4000);
    }
}

export function subscribeToToasts(listener: (items: ToastItem[]) => void): () => void {
    listeners.add(listener);
    listener([...toastItems]);

    return () => {
        listeners.delete(listener);
    };
}
