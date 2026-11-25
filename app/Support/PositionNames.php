<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Position names to suggest when labelling a player: the common ones, plus
 * anything the coach has already used in their formations.
 */
class PositionNames
{
    private const COMMON = ['QB', 'C', 'RB', 'X', 'Y', 'Z', 'SL', 'WR', 'TE', 'HB', 'FB', 'LB', 'CB', 'S', 'DE'];

    /**
     * @return array<int, string>
     */
    public static function for(User $user): array
    {
        $names = self::COMMON;

        foreach ($user->activeTeam()->formations()->get(['diagram']) as $formation) {
            foreach (Arr::pluck($formation->diagram['players'] ?? [], 'label') as $label) {
                if (is_string($label) && $label !== '') {
                    $names[] = $label;
                }
            }
        }

        $names = array_unique($names);
        sort($names);

        return $names;
    }
}
