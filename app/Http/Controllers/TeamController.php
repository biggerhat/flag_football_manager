<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * The active team: its members, pending invitations and the user's other teams.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $team = $user->activeTeam();
        $roles = DB::table('team_user')->where('team_id', $team->id)->pluck('role', 'user_id');
        $viaClub = DB::table('team_user')->where('team_id', $team->id)->where('via_club', true)->pluck('user_id')->all();

        return Inertia::render('teams/Show', [
            'team' => ['id' => $team->id, 'name' => $team->name, 'announcement' => $team->announcement, 'timezone' => $team->timezone ?: config('app.timezone')],
            'role' => $user->role(),
            'members' => $team->members()->orderBy('name')->get()->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $roles[$member->id] ?? Team::VIEWER,
                'via_club' => in_array($member->id, $viaClub, true),
            ]),
            'invitations' => $user->isOwner()
                ? $team->invitations()->latest()->get()->map(fn (TeamInvitation $invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'url' => $invitation->url(),
                    'expired' => $invitation->expired(),
                ])
                : [],
            'roles' => Team::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $team = Team::startFor($request->user(), $data['name'], seed: true);
        $request->user()->switchTeam($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team created.')]);

        return to_route('team.show');
    }

    public function switch(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user()->teams()->whereKey($team->id)->exists(), 404);

        $request->user()->switchTeam($team);

        return to_route('dashboard');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->ensureOwner($request, $team);

        $team->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return back();
    }

    /**
     * A short note every member sees on their dashboard; owners and coaches can post it.
     */
    public function announce(Request $request, Team $team): RedirectResponse
    {
        abort_unless($team->id === $request->user()->activeTeam()->id && $request->user()->canEdit(), 403);

        $team->update(['announcement' => $request->validate([
            'announcement' => ['nullable', 'string', 'max:500'],
        ])['announcement'] ?: null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement saved.')]);

        return back();
    }

    /**
     * Deleting a team removes everything it holds, so only the owner can.
     */
    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->ensureOwner($request, $team);

        $request->user()->forceFill(['current_team_id' => null])->save();
        $team->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team deleted.')]);

        return to_route('dashboard');
    }

    public function leave(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();
        $role = $team->roleOf($user);

        abort_if($role === null, 404);
        abort_if($role === Team::OWNER, 422, __('Owners cannot leave; delete the team instead.'));

        $team->members()->detach($user);
        $user->forceFill(['current_team_id' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the team.')]);

        return to_route('dashboard');
    }

    private function ensureOwner(Request $request, Team $team): void
    {
        $role = $team->roleOf($request->user());

        abort_unless($role === Team::OWNER, 404);
    }
}
