<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved earlier state of a play, kept so a bad edit can be undone.
 *
 * @property int $id
 * @property int $play_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $notes
 * @property array<string, mixed> $diagram
 * @property Carbon $created_at
 */
class PlayVersion extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['diagram' => 'array'];
    }

    /** @return BelongsTo<Play, $this> */
    public function play(): BelongsTo
    {
        return $this->belongsTo(Play::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
