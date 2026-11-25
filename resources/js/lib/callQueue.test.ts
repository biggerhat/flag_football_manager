import { beforeEach, describe, expect, it, vi } from 'vite-plus/test';
import { readQueue, writeQueue, type QueuedCall } from './callQueue';

const call = (key: string): QueuedCall => ({
    key,
    play_id: 1,
    play: 'Slant',
    player_id: null,
    result: 'gain',
    yards: 5,
    down: 1,
    note: null,
});

describe('callQueue', () => {
    beforeEach(() => {
        const store = new Map<string, string>();

        vi.stubGlobal('localStorage', {
            getItem: (k: string) => store.get(k) ?? null,
            setItem: (k: string, v: string) => void store.set(k, v),
            removeItem: (k: string) => void store.delete(k),
        });
    });

    it('returns an empty queue when nothing is stored', () => {
        expect(readQueue(1)).toEqual([]);
    });

    it('keeps calls in the order they were written', () => {
        writeQueue(1, [call('a'), call('b')]);

        expect(readQueue(1).map((c) => c.key)).toEqual(['a', 'b']);
    });

    it('keeps a separate queue per game plan', () => {
        writeQueue(1, [call('a')]);
        writeQueue(2, [call('b')]);

        expect(readQueue(1).map((c) => c.key)).toEqual(['a']);
        expect(readQueue(2).map((c) => c.key)).toEqual(['b']);
    });

    it('removes the entry when the queue is emptied', () => {
        writeQueue(1, [call('a')]);
        writeQueue(1, []);

        expect(localStorage.getItem('coach.callQueue.1')).toBeNull();
    });

    it('ignores corrupt stored data', () => {
        localStorage.setItem('coach.callQueue.1', '{not json');

        expect(readQueue(1)).toEqual([]);
    });
});
