<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A club: a named group of teams that its administrators manage together.
 *
 * Club members are given a role on every team in the club ("via_club" memberships):
 * administrators become owners, coaches coaches, viewers viewers. The rest of the app keeps
 * deciding access by team membership alone.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Organization extends Model
{
    public const OWNER = 'owner';

    public const ADMIN = 'admin';

    public const COACH = 'coach';

    public const VIEWER = 'viewer';

    /** Club roles that can be handed out (the owner is whoever started the club). */
    public const ROLES = [self::ADMIN, self::COACH, self::VIEWER];

    /**
     * The role a club role gives on every team in the club.
     */
    public static function teamRole(string $clubRole): string
    {
        return match ($clubRole) {
            self::OWNER, self::ADMIN => Team::OWNER,
            self::COACH => Team::COACH,
            default => Team::VIEWER,
        };
    }

    /**
     * The club role an invitation to a club team stands for.
     */
    public static function roleFromTeamRole(string $teamRole): string
    {
        return match ($teamRole) {
            Team::OWNER => self::ADMIN,
            Team::COACH => self::COACH,
            default => self::VIEWER,
        };
    }

    /** Whether the club role may manage the club (add teams, take them out). */
    public static function manages(?string $clubRole): bool
    {
        return in_array($clubRole, [self::OWNER, self::ADMIN], true);
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Owner and administrators.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function roleOf(User $user): ?string
    {
        $role = DB::table('organization_user')->where('organization_id', $this->id)->where('user_id', $user->id)->value('role');

        return is_string($role) ? $role : null;
    }

    /**
     * Put a team in this club; every club member is given the matching role on it.
     */
    public function addTeam(Team $team): void
    {
        $team->forceFill(['organization_id' => $this->id])->save();

        foreach ($this->members()->get() as $member) {
            $this->grantTeam($member, $team, (string) $this->roleOf($member));
        }
    }

    /**
     * Take a team out of the club. Access the club handed out goes with it.
     */
    public function removeTeam(Team $team): void
    {
        $team->members()->wherePivot('via_club', true)->detach();
        $team->forceFill(['organization_id' => null])->save();
        User::query()->where('current_team_id', $team->id)->whereDoesntHave('teams', fn ($q) => $q->whereKey($team->id))->update(['current_team_id' => null]);
    }

    /**
     * Add someone to the club with a role: administrators own every club team,
     * coaches can edit them, viewers can look.
     */
    public function addMember(User $user, string $role = self::ADMIN): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role]]);

        foreach ($this->teams()->get() as $team) {
            $this->grantTeam($user, $team, $role);
        }
    }

    /**
     * Change a member's club role; the access the club gave them follows.
     */
    public function changeRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, ['role' => $role]);

        foreach ($this->teams()->get() as $team) {
            $given = $team->members()->whereKey($user->id)->wherePivot('via_club', true)->exists();

            if ($given) {
                $team->members()->updateExistingPivot($user->id, ['role' => self::teamRole($role)]);
            } else {
                $this->grantTeam($user, $team, $role);
            }
        }
    }

    public function removeMember(User $user): void
    {
        foreach ($this->teams()->get() as $team) {
            $team->members()->wherePivot('via_club', true)->detach($user);
        }

        $this->members()->detach($user);
        $user->forceFill(['current_team_id' => null])->save();
    }

    private function grantTeam(User $user, Team $team, string $clubRole): void
    {
        if (! $team->members()->whereKey($user->id)->exists()) {
            $team->members()->attach($user, ['role' => self::teamRole($clubRole), 'via_club' => true]);
        }
    }
}
