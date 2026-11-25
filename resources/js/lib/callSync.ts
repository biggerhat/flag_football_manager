import type { QueuedCall } from '@/lib/callQueue';

/**
 * How one attempt to send a queued call ended:
 * - `sent`: the server saved it.
 * - `rejected`: the server refused it for good (e.g. the play was deleted), so retrying is pointless.
 * - `unreachable`: no connection or a server error; keep it and try again later.
 */
export type SendOutcome = 'sent' | 'rejected' | 'unreachable';

/**
 * Send queued calls oldest first. Sent and rejected calls leave the queue; the first
 * unreachable one stops the run, keeping it and everything after it in order.
 */
export async function drainQueue(
    queue: QueuedCall[],
    send: (call: QueuedCall) => Promise<SendOutcome>,
): Promise<{ remaining: QueuedCall[]; sent: number }> {
    let sent = 0;

    for (let i = 0; i < queue.length; i++) {
        const outcome = await send(queue[i]);

        if (outcome === 'unreachable') {
            return { remaining: queue.slice(i), sent };
        }

        if (outcome === 'sent') {
            sent++;
        }
    }

    return { remaining: [], sent };
}

/** Whether a failed HTTP status is worth retrying (server trouble) rather than dropping (bad request). */
export const isRetryableStatus = (status: number): boolean => status >= 500;
