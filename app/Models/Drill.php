<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property string $name
 * @property string $category
 * @property int $duration
 * @property int|null $players
 * @property string|null $equipment
 * @property string|null $instructions
 * @property string|null $coaching_points
 * @property array<string, mixed>|null $diagram
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'category', 'duration', 'players', 'equipment', 'instructions', 'coaching_points', 'diagram'])]
class Drill extends Model
{
    use BelongsToTeam, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['diagram' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
