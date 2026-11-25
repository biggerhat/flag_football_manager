import { describe, expect, it } from 'vite-plus/test';
import { drainQueue, isRetryableStatus, type SendOutcome } from './callSync';
import type { QueuedCall } from './callQueue';

const call = (key: string): QueuedCall => ({
    key,
    play_id: 1,
    play: 'Slant',
    player_id: null,
    result: 'gain',
    yards: 5,
    down: null,
    note: null,
});

const sender = (outcomes: Record<string, SendOutcome>) => {
    const order: string[] = [];

    return {
        order,
        send: async (c: QueuedCall) => {
            order.push(c.key);

            return outcomes[c.key] ?? 'sent';
        },
    };
};

describe('drainQueue', () => {
    it('sends everything oldest first and empties the queue', async () => {
        const { order, send } = sender({});
        const result = await drainQueue(
            [call('a'), call('b'), call('c')],
            send,
        );

        expect(order).toEqual(['a', 'b', 'c']);
        expect(result).toEqual({ remaining: [], sent: 3 });
    });

    it('drops a call the server rejected and carries on', async () => {
        const { order, send } = sender({ b: 'rejected' });
        const result = await drainQueue(
            [call('a'), call('b'), call('c')],
            send,
        );

        expect(order).toEqual(['a', 'b', 'c']);
        expect(result.remaining).toEqual([]);
        expect(result.sent).toBe(2);
    });

    it('stops at the first unreachable call and keeps it and the rest in order', async () => {
        const { order, send } = sender({ b: 'unreachable' });
        const result = await drainQueue(
            [call('a'), call('b'), call('c')],
            send,
        );

        expect(order).toEqual(['a', 'b']);
        expect(result.remaining.map((c) => c.key)).toEqual(['b', 'c']);
        expect(result.sent).toBe(1);
    });

    it('does nothing for an empty queue', async () => {
        const { order, send } = sender({});

        expect(await drainQueue([], send)).toEqual({ remaining: [], sent: 0 });
        expect(order).toEqual([]);
    });
});

describe('isRetryableStatus', () => {
    it('retries server errors but not client errors', () => {
        expect(isRetryableStatus(500)).toBe(true);
        expect(isRetryableStatus(503)).toBe(true);
        expect(isRetryableStatus(404)).toBe(false);
        expect(isRetryableStatus(422)).toBe(false);
    });
});
