<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property int|null $invited_by
 * @property string|null $batch
 * @property int|null $organization_id
 * @property CarbonInterface $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'role', 'invited_by', 'batch', 'organization_id'])]
class TeamInvitation extends Model
{
    protected static function booted(): void
    {
        static::creating(function (TeamInvitation $invitation) {
            $invitation->token = Str::random(48);
            $invitation->expires_at = now()->addDays(14);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function url(): string
    {
        return route('invitations.show', $this->token);
    }
}
