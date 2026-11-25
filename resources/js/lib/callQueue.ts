/**
 * Game-day calls logged without a connection wait here (in this browser) and
 * are sent once the connection comes back.
 */
export type QueuedCall = {
    key: string;
    play_id: number;
    play: string;
    player_id: number | null;
    result: string;
    yards: number | null;
    down: number | null;
    note: string | null;
};

const storageKey = (gamePlanId: number) => `coach.callQueue.${gamePlanId}`;

export function readQueue(gamePlanId: number): QueuedCall[] {
    try {
        const raw = localStorage.getItem(storageKey(gamePlanId));

        return raw ? (JSON.parse(raw) as QueuedCall[]) : [];
    } catch {
        return [];
    }
}

export function writeQueue(gamePlanId: number, queue: QueuedCall[]): void {
    try {
        if (queue.length) {
            localStorage.setItem(storageKey(gamePlanId), JSON.stringify(queue));
        } else {
            localStorage.removeItem(storageKey(gamePlanId));
        }
    } catch {
        // Storage can be blocked (private mode); calls then just aren't kept offline.
    }
}
