<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    /**
     * Invite someone by email. They get a link to share; accepting it adds them to the team.
     */
    public function invite(Request $request): RedirectResponse
    {
        $team = $this->ownedTeam($request);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(Team::ROLES)],
            'team_ids' => ['sometimes', 'array', 'max:20'],
            'team_ids.*' => ['integer'],
        ]);

        $email = strtolower($data['email']);

        if ($team->members()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return back()->withErrors(['email' => __('That person is already on the team.')]);
        }

        // Other teams the inviter owns can be added to the same invitation.
        $others = $request->user()->teams()->wherePivot('role', Team::OWNER)->whereIn('teams.id', $data['team_ids'] ?? [])->where('teams.id', '!=', $team->id)->get()
            ->reject(fn (Team $t) => $t->members()->whereRaw('lower(email) = ?', [$email])->exists());
        $batch = Str::random(32);

        foreach ($others->prepend($team) as $target) {
            $target->invitations()->whereRaw('lower(email) = ?', [$email])->delete();
            $target->invitations()->create(['email' => $email, 'role' => $data['role'], 'invited_by' => $request->user()->id, 'batch' => $batch]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation created. Share the link with them.')]);

        return back();
    }

    public function cancelInvitation(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $team = $this->ownedTeam($request);
        abort_unless($invitation->team_id === $team->id, 404);

        $invitation->delete();

        return back();
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $team = $this->ownedTeam($request);
        $this->ensureEditable($team, $member);

        $data = $request->validate(['role' => ['required', Rule::in(Team::ROLES)]]);
        $team->members()->updateExistingPivot($member->id, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $team = $this->ownedTeam($request);
        $this->ensureEditable($team, $member);

        $team->members()->detach($member);
        User::whereKey($member->id)->where('current_team_id', $team->id)->update(['current_team_id' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return back();
    }

    private function ownedTeam(Request $request): Team
    {
        abort_unless($request->user()->isOwner(), 403);

        return $request->user()->activeTeam();
    }

    /**
     * Only existing non-owner members can be changed.
     */
    private function ensureEditable(Team $team, User $member): void
    {
        $role = $team->roleOf($member);

        abort_if($role === null, 404);
        abort_if($role === Team::OWNER, 422, __('The owner cannot be changed.'));
        abort_if($team->members()->whereKey($member->id)->wherePivot('via_club', true)->exists(), 422, __('This access comes from the club. Change it on the Club page.'));
    }
}
