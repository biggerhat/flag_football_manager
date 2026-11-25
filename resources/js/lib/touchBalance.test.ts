import { describe, expect, it } from 'vite-plus/test';
import { touchBalance } from './touchBalance';

const players = [
    { id: 1, name: 'Zed', number: '1' },
    { id: 2, name: 'Amy', number: null },
    { id: 3, name: 'Bo', number: '3' },
];

describe('touchBalance', () => {
    it('lists players with no touches first, alphabetically', () => {
        const result = touchBalance(players, [{ player_id: 1 }]);

        expect(result.map((p) => [p.name, p.touches])).toEqual([
            ['Amy', 0],
            ['Bo', 0],
            ['Zed', 1],
        ]);
    });

    it('counts every call and ignores calls with no player or an unknown one', () => {
        const result = touchBalance(players, [
            { player_id: 3 },
            { player_id: 3 },
            { player_id: null },
            { player_id: 99 },
        ]);

        expect(result.find((p) => p.id === 3)?.touches).toBe(2);
        expect(result.reduce((sum, p) => sum + p.touches, 0)).toBe(2);
    });
});
