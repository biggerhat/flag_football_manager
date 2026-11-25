<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $practice_id
 * @property int|null $drill_id
 * @property int|null $play_id
 * @property string $title
 * @property int $duration
 * @property string|null $notes
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['drill_id', 'play_id', 'title', 'duration', 'notes', 'sort_order'])]
class PracticeSegment extends Model
{
    /**
     * @return BelongsTo<Practice, $this>
     */
    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    /**
     * @return BelongsTo<Drill, $this>
     */
    public function drill(): BelongsTo
    {
        return $this->belongsTo(Drill::class);
    }

    /**
     * @return BelongsTo<Play, $this>
     */
    public function play(): BelongsTo
    {
        return $this->belongsTo(Play::class);
    }
}
