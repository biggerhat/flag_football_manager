type Person = { id: number; name: string; number: string | null };

/**
 * Touches per rostered player, fewest first (ties by name), so nobody gets forgotten.
 */
export function touchBalance<P extends Person>(
    players: P[],
    calls: { player_id: number | null }[],
): (P & { touches: number })[] {
    const counts = new Map<number, number>();

    for (const call of calls) {
        if (call.player_id) {
            counts.set(call.player_id, (counts.get(call.player_id) ?? 0) + 1);
        }
    }

    return players
        .map((p) => ({ ...p, touches: counts.get(p.id) ?? 0 }))
        .sort((a, b) => a.touches - b.touches || a.name.localeCompare(b.name));
}
