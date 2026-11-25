<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property bool $email_reminders
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    private ?Team $activeTeam = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'email_reminders' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Play, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }

    /**
     * @return HasMany<Playbook, $this>
     */
    public function playbooks(): HasMany
    {
        return $this->hasMany(Playbook::class);
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('role', 'via_club')->withTimestamps();
    }

    /**
     * The team whose playbooks and plans the user is working in. Every user always has
     * one: a personal team is started the first time it is needed.
     */
    public function activeTeam(): Team
    {
        if ($this->activeTeam) {
            return $this->activeTeam;
        }

        $team = $this->current_team_id ? $this->teams()->whereKey($this->current_team_id)->first() : null;
        $team ??= $this->teams()->oldest('teams.id')->first();

        if (! $team) {
            $started = Team::startFor($this, "{$this->name}'s team");
            $team = $this->teams()->whereKey($started->id)->firstOrFail();
        }

        if ($this->current_team_id !== $team->id) {
            $this->forceFill(['current_team_id' => $team->id])->saveQuietly();
        }

        return $this->activeTeam = $team;
    }

    /**
     * Before an account is deleted: hand each team they own to its longest-standing
     * other member, or delete the team when nobody else is on it.
     */
    public function releaseTeams(): void
    {
        foreach ($this->teams()->get() as $team) {
            if ($team->roleOf($this) !== Team::OWNER) {
                continue;
            }

            $next = $team->members()->whereKeyNot($this->id)->orderBy('team_user.id')->first();

            if ($next) {
                $team->members()->updateExistingPivot($next->id, ['role' => Team::OWNER]);
            } else {
                $team->delete();
            }
        }
    }

    public function switchTeam(Team $team): void
    {
        $this->forceFill(['current_team_id' => $team->id])->save();
        $this->activeTeam = null;
    }

    /**
     * The user's role in each of their teams, keyed by team id.
     *
     * @return array<int, string>
     */
    public function teamRoles(): array
    {
        $roles = [];

        foreach (DB::table('team_user')->where('user_id', $this->id)->get(['team_id', 'role']) as $row) {
            $roles[(int) $row->team_id] = (string) $row->role;
        }

        return $roles;
    }

    /**
     * The user's role in their active team: owner, coach or viewer.
     */
    public function role(): string
    {
        return $this->activeTeam()->roleOf($this) ?? Team::VIEWER;
    }

    public function isOwner(): bool
    {
        return $this->role() === Team::OWNER;
    }

    /**
     * Owners and coaches can change team content; viewers can only look and print.
     */
    public function canEdit(): bool
    {
        return $this->role() !== Team::VIEWER;
    }

    /**
     * @return HasMany<Drill, $this>
     */
    public function drills(): HasMany
    {
        return $this->hasMany(Drill::class);
    }

    /**
     * @return HasMany<GamePlan, $this>
     */
    public function gamePlans(): HasMany
    {
        return $this->hasMany(GamePlan::class);
    }

    /**
     * @return HasMany<Practice, $this>
     */
    public function practices(): HasMany
    {
        return $this->hasMany(Practice::class);
    }

    /**
     * @return HasMany<Formation, $this>
     */
    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }
}
