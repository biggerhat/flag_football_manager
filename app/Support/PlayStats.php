<?php

namespace App\Support;

use App\Models\PlayCall;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * How each play has performed in the games it was called in.
 */
class PlayStats
{
    /**
     * @return array<int, array{calls: int, yards: int, touchdowns: int, success: int}> keyed by play id
     */
    public static function forTeam(Team $team): array
    {
        $success = "'".implode("','", PlayCall::SUCCESSES)."'";

        return DB::table('play_calls')
            ->where('team_id', $team->id)
            ->groupBy('play_id')
            ->select('play_id', DB::raw('count(*) as calls'), DB::raw('coalesce(sum(yards), 0) as yards'),
                DB::raw("sum(case when result = 'touchdown' then 1 else 0 end) as touchdowns"),
                DB::raw("sum(case when result in ({$success}) then 1 else 0 end) as success"))
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->play_id => [
                'calls' => (int) $row->calls,
                'yards' => (int) $row->yards,
                'touchdowns' => (int) $row->touchdowns,
                'success' => (int) $row->success,
            ]])
            ->all();
    }
}
