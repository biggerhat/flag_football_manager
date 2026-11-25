<?php

namespace App\Support;

class DefaultFormations
{
    private const LOS = 0.68;

    /**
     * Every built-in formation, offensive and defensive.
     *
     * @return array<int, array{name: string, description: string, diagram: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [...self::offense(), ...self::defense()];
    }

    /**
     * The 5v5 offensive formations every coach starts with.
     *
     * @return array<int, array{name: string, description: string, diagram: array<string, mixed>}>
     */
    public static function offense(): array
    {
        $los = self::LOS;

        return [
            self::formation('Spread', 'Three receivers stretched wide', [
                ['center', 'C', 0.5, $los],
                ['quarterback', 'QB', 0.5, $los + 0.1],
                ['receiver', 'X', 0.1, $los],
                ['receiver', 'Y', 0.9, $los],
                ['receiver', 'Z', 0.3, $los + 0.02],
            ]),
            self::formation('Bunch', 'Three receivers clustered to one side', [
                ['center', 'C', 0.5, $los],
                ['quarterback', 'QB', 0.5, $los + 0.1],
                ['receiver', 'X', 0.76, $los],
                ['receiver', 'Y', 0.84, $los + 0.05],
                ['receiver', 'Z', 0.84, $los],
            ]),
            self::formation('Split Back', 'Two backs split behind the QB', [
                ['center', 'C', 0.5, $los],
                ['quarterback', 'QB', 0.5, $los + 0.08],
                ['receiver', 'X', 0.12, $los],
                ['receiver', 'Y', 0.32, $los + 0.16],
                ['receiver', 'Z', 0.68, $los + 0.16],
            ]),
            self::formation('Single Back', 'One back behind the QB, two wide', [
                ['center', 'C', 0.5, $los],
                ['quarterback', 'QB', 0.5, $los + 0.08],
                ['receiver', 'X', 0.1, $los],
                ['receiver', 'Y', 0.9, $los],
                ['receiver', 'Z', 0.5, $los + 0.18],
            ]),
        ];
    }

    /**
     * 5v5 defensive alignments, placed on the defense's side of the line of scrimmage.
     *
     * @return array<int, array{name: string, description: string, diagram: array<string, mixed>}>
     */
    public static function defense(): array
    {
        return [
            self::formation('Zone 2-3', 'Two deep, three short zones', [
                ['defender', 'D1', 0.3, 0.4],
                ['defender', 'D2', 0.7, 0.4],
                ['defender', 'D3', 0.2, 0.58],
                ['defender', 'D4', 0.5, 0.58],
                ['defender', 'D5', 0.8, 0.58],
            ], '#dc2626'),
            self::formation('Man', 'One defender on each receiver', [
                ['defender', 'D1', 0.1, 0.6],
                ['defender', 'D2', 0.3, 0.6],
                ['defender', 'D3', 0.5, 0.52],
                ['defender', 'D4', 0.7, 0.6],
                ['defender', 'D5', 0.9, 0.6],
            ], '#dc2626'),
            self::formation('Blitz', 'Three rush, two deep', [
                ['defender', 'D1', 0.5, 0.62],
                ['defender', 'D2', 0.2, 0.6],
                ['defender', 'D3', 0.8, 0.6],
                ['defender', 'D4', 0.35, 0.42],
                ['defender', 'D5', 0.65, 0.42],
            ], '#dc2626'),
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: float, 3: float}>  $players
     * @return array{name: string, description: string, diagram: array<string, mixed>}
     */
    private static function formation(string $name, string $description, array $players, string $color = '#2563eb'): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'diagram' => [
                'schema_version' => 1,
                'los' => self::LOS,
                'players' => array_map(fn (array $p) => [
                    'id' => 'p-'.strtolower($p[1]),
                    'type' => $p[0],
                    'label' => $p[1],
                    'x' => $p[2],
                    'y' => round($p[3], 2),
                    'color' => $color,
                ], $players),
                'routes' => [],
                'annotations' => [],
            ],
        ];
    }
}
