<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property int|null $playbook_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $formation
 * @property string $category
 * @property string $difficulty
 * @property bool $beginner_friendly
 * @property bool $is_favorite
 * @property array<int, string>|null $tags
 * @property string|null $folder
 * @property string|null $notes
 * @property array<string, mixed> $diagram
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['playbook_id', 'name', 'formation', 'category', 'difficulty', 'beginner_friendly', 'tags', 'folder', 'notes', 'diagram'])]
class Play extends Model
{
    use BelongsToTeam, SoftDeletes;

    protected static function booted(): void
    {
        // A play always lives in a playbook; without a choice it goes in the team's first one.
        static::creating(function (Play $play) {
            $play->playbook_id ??= Team::findOrFail($play->team_id)->defaultPlaybook($play->user_id)->id;
        });
    }

    /**
     * @return array<string, string>
     */
    /** How many earlier versions of a play are kept. */
    public const KEEP_VERSIONS = 20;

    /**
     * @return HasMany<PlayVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PlayVersion::class)->latest('created_at')->latest('id');
    }

    /**
     * Before saving, keep the state about to be overwritten as a version, but only when
     * something a version shows (name, notes, diagram) actually changed.
     */
    public function recordVersionIfChanged(?int $userId): void
    {
        if (! $this->isDirty(['name', 'notes', 'diagram'])) {
            return;
        }

        $this->versions()->create([
            'user_id' => $userId,
            'name' => $this->getOriginal('name'),
            'notes' => $this->getOriginal('notes'),
            'diagram' => $this->getOriginal('diagram'),
        ]);

        PlayVersion::whereIn('id', $this->versions()->skip(self::KEEP_VERSIONS)->take(PHP_INT_MAX)->pluck('id'))->delete();
    }

    protected function casts(): array
    {
        return [
            'beginner_friendly' => 'boolean',
            'is_favorite' => 'boolean',
            'tags' => 'array',
            'diagram' => 'array',
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
     * @return BelongsTo<Playbook, $this>
     */
    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    /**
     * The play this one is a variation of.
     *
     * @return BelongsTo<Play, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
