<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property string|null $share_token
 * @property Carbon|null $share_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description'])]
class Playbook extends Model
{
    use BelongsToTeam;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['share_expires_at' => 'datetime'];
    }

    /** Whether the public share link exists and has not expired (links made before expiry existed never do). */
    public function shareIsLive(): bool
    {
        return $this->share_token !== null && ($this->share_expires_at === null || $this->share_expires_at->isFuture());
    }

    /**
     * The shape shared by the packet page, whether opened by the team or through a share link.
     *
     * @return array<string, mixed>
     */
    public function packet(): array
    {
        return [
            'team' => $this->team->name,
            'playbook' => $this->only(['id', 'name', 'description']),
            'plays' => $this->plays()->orderBy('folder')->orderBy('name')
                ->get(['id', 'name', 'formation', 'category', 'difficulty', 'folder', 'notes', 'diagram']),
            'generatedAt' => now()->toDateString(),
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
     * @return HasMany<Play, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }
}
