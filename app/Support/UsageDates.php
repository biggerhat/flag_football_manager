<?php

namespace App\Support;

use App\Models\PracticeSegment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * When each drill and play was last run in a real (dated, non-template) practice.
 */
class UsageDates
{
    /**
     * @return Collection<int, string> drill id => Y-m-d
     */
    public static function drills(User $user): Collection
    {
        return self::lastRun($user, 'drill_id');
    }

    /**
     * @return Collection<int, string> play id => Y-m-d
     */
    public static function plays(User $user): Collection
    {
        return self::lastRun($user, 'play_id');
    }

    /**
     * @param  'drill_id'|'play_id'  $column
     * @return Collection<int, string>
     */
    private static function lastRun(User $user, string $column): Collection
    {
        return PracticeSegment::query()
            ->join('practices', 'practices.id', '=', 'practice_segments.practice_id')
            ->where('practices.team_id', $user->activeTeam()->id)
            ->where('practices.is_template', false)
            ->whereDate('practices.date', '<=', $user->activeTeam()->today())
            ->whereNotNull("practice_segments.{$column}")
            ->groupBy("practice_segments.{$column}")
            ->selectRaw("practice_segments.{$column} as item_id, max(practices.date) as last_run")
            ->pluck('last_run', 'item_id')
            ->map(fn ($date) => substr((string) $date, 0, 10));
    }
}
