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
 * @property Carbon|null $date
 * @property string|null $start_time
 * @property int $duration
 * @property string|null $location
 * @property string|null $objectives
 * @property string|null $notes
 * @property string|null $review_notes
 * @property bool $is_template
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'date', 'start_time', 'duration', 'location', 'objectives', 'notes', 'review_notes', 'is_template'])]
class Practice extends Model
{
    use BelongsToTeam, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_template' => 'boolean',
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
     * Players marked present at the practice.
     *
     * @return BelongsToMany<Player, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'attendances');
    }

    /**
     * @return HasMany<PracticeSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(PracticeSegment::class)->orderBy('sort_order');
    }
}
