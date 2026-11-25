<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A stretch of dates (a fall league, say) that records, stats and attendance can be limited to.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 */
#[Fillable(['name', 'starts_on', 'ends_on'])]
class Season extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d'];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Limit a query to dates inside this season.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Eloquent\Relations\Relation<*, *, *>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function constrain($query, string $column)
    {
        $query->whereDate($column, '>=', $this->starts_on->toDateString());

        if ($this->ends_on) {
            $query->whereDate($column, '<=', $this->ends_on->toDateString());
        }

        return $query;
    }
}
