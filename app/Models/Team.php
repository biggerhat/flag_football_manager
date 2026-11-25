<?php

namespace App\Models;

use App\Actions\SeedStarterContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string|null $calendar_token
 * @property string|null $timezone
 * @property int|null $organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'announcement', 'timezone'])]
class Team extends Model
{
    public const OWNER = 'owner';

    public const COACH = 'coach';

    public const VIEWER = 'viewer';

    /** Roles that can be handed out; the owner is whoever created the team. */
    public const ROLES = [self::COACH, self::VIEWER];

    /**
     * Create a team with the user as its owner and give it starter content.
     */
    public static function startFor(User $user, string $name, bool $seed = false): self
    {
        $team = self::create(['name' => $name]);
        $team->members()->attach($user, ['role' => self::OWNER]);

        if ($seed) {
            app(SeedStarterContent::class)->seed($team, $user);
        }

        return $team;
    }

    /**
     * The team's calendar date right now. Games and practices are dates in the
     * team's own timezone, so "today" must be too, not the server's.
     */
    public function today(): Carbon
    {
        return Carbon::today($this->timezone ?: config('app.timezone'));
    }

    /**
     * The user's role on this team, or null when they are not a member.
     */
    public function roleOf(User $user): ?string
    {
        $role = DB::table('team_user')->where('team_id', $this->id)->where('user_id', $user->id)->value('role');

        return is_string($role) ? $role : null;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'via_club')->withTimestamps();
    }

    /**
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class)->orderByDesc('starts_on');
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * Who plays each position, for printing names next to assignments: the first
     * active player listed for a position wins.
     *
     * @return array<string, string>
     */
    public function lineup(): array
    {
        $lineup = [];

        foreach ($this->players()->where('active', true)->whereNotNull('position')->orderBy('name')->get(['name', 'number', 'position']) as $player) {
            $lineup[$player->position] ??= $player->number ? "#{$player->number} {$player->name}" : $player->name;
        }

        return $lineup;
    }

    /**
     * @return HasMany<PlayCall, $this>
     */
    public function playCalls(): HasMany
    {
        return $this->hasMany(PlayCall::class);
    }

    /**
     * @return HasMany<Playbook, $this>
     */
    public function playbooks(): HasMany
    {
        return $this->hasMany(Playbook::class);
    }

    /**
     * @return HasMany<Play, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(Play::class);
    }

    /**
     * @return HasMany<Drill, $this>
     */
    public function drills(): HasMany
    {
        return $this->hasMany(Drill::class);
    }

    /**
     * @return HasMany<Formation, $this>
     */
    public function formations(): HasMany
    {
        return $this->hasMany(Formation::class);
    }

    /**
     * @return HasMany<Practice, $this>
     */
    public function practices(): HasMany
    {
        return $this->hasMany(Practice::class);
    }

    /**
     * @return HasMany<GamePlan, $this>
     */
    public function gamePlans(): HasMany
    {
        return $this->hasMany(GamePlan::class);
    }

    /**
     * The team's first playbook, created on demand so plays always have a home.
     */
    public function defaultPlaybook(?int $userId = null): Playbook
    {
        return $this->playbooks()->oldest('id')->first()
            ?? $this->playbooks()->forceCreate(['name' => 'My playbook', 'user_id' => $userId ?? Auth::id()]);
    }
}
