<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClubController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $club = $this->clubOf($user);

        if (! $club) {
            return Inertia::render('club/Show', ['club' => null]);
        }

        $myRole = (string) $club->roleOf($user);
        $isOwner = $myRole === Organization::OWNER;
        $canManage = Organization::manages($myRole);
        $clubRoles = DB::table('organization_user')->where('organization_id', $club->id)->pluck('role', 'user_id');

        return Inertia::render('club/Show', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'isOwner' => $isOwner,
                'canManage' => $canManage,
                'roles' => Organization::ROLES,
                'teams' => $club->teams()->withCount('members')->orderBy('name')->get()
                    ->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name, 'members' => $t->members_count]),
                'members' => $club->members()->orderBy('name')->get()
                    ->map(fn (User $m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'role' => $clubRoles[$m->id] ?? Organization::VIEWER]),
                'invitations' => $isOwner ? TeamInvitation::query()->where('organization_id', $club->id)->get()->unique('batch')
                    ->map(fn (TeamInvitation $i) => ['id' => $i->id, 'email' => $i->email, 'url' => $i->url(), 'expired' => $i->expired()])->values() : [],
                // Teams the person owns outright that could join the club.
                'addable' => ! $canManage ? [] : $user->teams()->wherePivot('role', Team::OWNER)->wherePivot('via_club', false)->whereNull('teams.organization_id')->orderBy('name')->get(['teams.id', 'teams.name'])
                    ->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name]),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        abort_if($this->clubOf($request->user()) !== null, 422, __('You already belong to a club.'));

        $club = Organization::create($data);
        $club->addMember($request->user(), Organization::OWNER);

        return to_route('club.show');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $club = $this->ownedClub($request);

        foreach ($club->teams()->get() as $team) {
            $club->removeTeam($team);
        }

        $club->members()->detach();
        $club->delete();

        return to_route('club.show');
    }

    public function addTeam(Request $request): RedirectResponse
    {
        $club = $this->managedClub($request);
        $data = $request->validate(['team_id' => ['required', 'integer']]);

        $team = $request->user()->teams()->wherePivot('role', Team::OWNER)->wherePivot('via_club', false)->whereNull('teams.organization_id')->whereKey($data['team_id'])->firstOrFail();
        $club->addTeam($team);

        return back();
    }

    public function removeTeam(Request $request, Team $team): RedirectResponse
    {
        $club = $this->managedClub($request);
        abort_unless($team->organization_id === $club->id, 404);

        $club->removeTeam($team);
        $request->user()->forceFill(['current_team_id' => null])->saveQuietly();

        return back();
    }

    /**
     * Invite someone to the club: one link gives them a role on every club team
     * (administrator: owner, coach: coach, viewer: viewer).
     */
    public function inviteMember(Request $request): RedirectResponse
    {
        $club = $this->ownedClub($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(Organization::ROLES)],
        ]);
        $email = strtolower($data['email']);

        $teams = $club->teams()->get();
        abort_if($teams->isEmpty(), 422, __('Add a team to the club first.'));

        if ($club->members()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withErrors(['email' => __('That person is already in the club.')]);
        }

        TeamInvitation::query()->where('organization_id', $club->id)->whereRaw('lower(email) = ?', [$email])->delete();

        $batch = Str::random(32);
        foreach ($teams as $team) {
            $team->invitations()->create(['email' => $email, 'role' => Organization::teamRole($data['role']), 'invited_by' => $request->user()->id, 'batch' => $batch, 'organization_id' => $club->id]);
        }

        return back();
    }

    public function updateMember(Request $request, User $member): RedirectResponse
    {
        $club = $this->ownedClub($request);
        $data = $request->validate(['role' => ['required', Rule::in(Organization::ROLES)]]);
        abort_unless(in_array($club->roleOf($member), Organization::ROLES, true), 404);

        $club->changeRole($member, $data['role']);

        return back();
    }

    public function cancelInvitation(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $club = $this->ownedClub($request);
        abort_unless($invitation->organization_id === $club->id, 404);

        TeamInvitation::query()->where('batch', $invitation->batch)->delete();

        return back();
    }

    public function removeMember(Request $request, User $member): RedirectResponse
    {
        $club = $this->ownedClub($request);
        abort_unless(in_array($club->roleOf($member), Organization::ROLES, true), 404);

        $club->removeMember($member);

        return back();
    }

    private function clubOf(User $user): ?Organization
    {
        return Organization::query()->whereHas('members', fn ($q) => $q->whereKey($user->id))->first();
    }

    /** The person's club, when they are allowed to manage it (owner or administrator). */
    private function managedClub(Request $request): Organization
    {
        $club = $this->clubOf($request->user()) ?? abort(404);
        abort_unless(Organization::manages($club->roleOf($request->user())), 404);

        return $club;
    }

    private function ownedClub(Request $request): Organization
    {
        $club = $this->clubOf($request->user()) ?? abort(404);
        abort_unless($club->roleOf($request->user()) === Organization::OWNER, 404);

        return $club;
    }
}
