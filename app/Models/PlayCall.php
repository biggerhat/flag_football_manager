<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One time a play was called in a game, and how it went.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $game_plan_id
 * @property int $play_id
 * @property int|null $player_id
 * @property string $result
 * @property int|null $yards
 * @property int|null $down
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['play_id', 'player_id', 'result', 'yards', 'down', 'note', 'client_key'])]
class PlayCall extends Model
{
    public const RESULTS = ['touchdown', 'gain', 'no_gain', 'loss', 'incomplete', 'turnover'];

    /** Results that count as the play working. */
    public const SUCCESSES = ['touchdown', 'gain'];

    /**
     * @return BelongsTo<Play, $this>
     */
    public function play(): BelongsTo
    {
        return $this->belongsTo(Play::class)->withTrashed();
    }

    /**
     * Who had the ball, when it was recorded.
     *
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<GamePlan, $this>
     */
    public function gamePlan(): BelongsTo
    {
        return $this->belongsTo(GamePlan::class);
    }
}
