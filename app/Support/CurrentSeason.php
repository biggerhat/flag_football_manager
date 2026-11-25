<?php

namespace App\Support;

use App\Models\Season;
use Illuminate\Http\Request;

/**
 * The season the person has chosen to look at (kept in their session), or null for all time.
 */
class CurrentSeason
{
    public static function for(Request $request): ?Season
    {
        $id = $request->session()->get('season_id');

        if (! $id || ! $request->user()) {
            return null;
        }

        return $request->user()->activeTeam()->seasons()->find((int) $id);
    }

    /**
     * Limit a query to the chosen season's dates (no change when viewing all time).
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function constrain(Request $request, $query, string $column)
    {
        return self::for($request)?->constrain($query, $column) ?? $query;
    }
}
