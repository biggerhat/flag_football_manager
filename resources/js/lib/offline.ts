import { usePage } from '@inertiajs/vue3';

export const offlineSupported = (): boolean =>
    'serviceWorker' in navigator && 'caches' in window;

export function registerServiceWorker(): void {
    if (!offlineSupported() || !import.meta.env.PROD) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Offline support is a bonus; the app works the same without it.
        });
    });
}

/** Asks the worker to forget saved pages, e.g. when logging out. */
export function clearOfflinePages(): void {
    navigator.serviceWorker?.controller?.postMessage('clear');
}

/**
 * Opens each URL the way the browser and Inertia would, so the worker keeps a
 * copy of both and the pages can be read without a connection. Returns how many
 * were saved.
 */
export async function savePagesOffline(urls: string[]): Promise<number> {
    const version = usePage().version ?? '';
    let saved = 0;

    for (const url of urls) {
        try {
            const [html, data] = await Promise.all([
                fetch(url, { headers: { Accept: 'text/html' } }),
                fetch(url, {
                    headers: {
                        Accept: 'text/html, application/xhtml+xml',
                        'X-Inertia': 'true',
                        'X-Inertia-Version': version,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }),
            ]);

            if (html.ok && data.ok) {
                saved++;
            }
        } catch {
            // Skip pages that fail; the rest still help.
        }
    }

    return saved;
}
