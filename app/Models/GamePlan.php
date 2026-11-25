<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property string $name
 * @property string|null $opponent
 * @property Carbon|null $date
 * @property string|null $notes
 * @property string|null $location
 * @property string|null $kickoff
 * @property int|null $score_for
 * @property int|null $score_against
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'opponent', 'date', 'notes', 'location', 'kickoff', 'score_for', 'score_against'])]
class GamePlan extends Model
{
    use BelongsToTeam, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PlayCall, $this>
     */
    public function calls(): HasMany
    {
        return $this->hasMany(PlayCall::class);
    }

    /**
     * W, L or T once both scores are in.
     */
    public function result(): ?string
    {
        if ($this->score_for === null || $this->score_against === null) {
            return null;
        }

        return match (true) {
            $this->score_for > $this->score_against => 'W',
            $this->score_for < $this->score_against => 'L',
            default => 'T',
        };
    }

    /**
     * Plays in the order they will be called.
     *
     * @return BelongsToMany<Play, $this>
     */
    public function plays(): BelongsToMany
    {
        return $this->belongsToMany(Play::class, 'game_plan_play')->orderByPivot('sort_order');
    }
}
