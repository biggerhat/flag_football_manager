<?php

namespace App\Models\Concerns;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Content shared by a whole team. `user_id` records who created it.
 */
trait BelongsToTeam
{
    public static function bootBelongsToTeam(): void
    {
        static::creating(function ($model) {
            $model->user_id ??= Auth::id();

            if ($model->team_id === null) {
                $model->team_id = User::query()->whereKey($model->user_id)->firstOrFail()->activeTeam()->id;
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
