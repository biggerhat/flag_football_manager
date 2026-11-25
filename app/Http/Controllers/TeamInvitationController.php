<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class TeamInvitationController extends Controller
{
    /**
     * The landing page for an invitation link; works for guests and signed-in users.
     */
    public function show(Request $request, string $token): Response
    {
        $invitation = TeamInvitation::with('team')->where('token', $token)->firstOrFail();

        // Come back here after logging in or registering.
        if ($request->user() === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('teams/Invitation', [
            'token' => $token,
            'team' => $invitation->team->name,
            'role' => $invitation->role,
            'club' => $invitation->organization_id ? Organization::find($invitation->organization_id)?->name : null,
            'email' => $invitation->email,
            'expired' => $invitation->expired(),
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = TeamInvitation::where('token', $token)->firstOrFail();
        $user = $request->user();

        if ($invitation->expired()) {
            return back()->withErrors(['invitation' => __('This invitation has expired. Ask for a new one.')]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return back()->withErrors(['invitation' => __('This invitation was sent to :email. Log in with that address.', ['email' => $invitation->email])]);
        }

        $team = $invitation->team;

        // Everything sent together (one person, several teams) is accepted together.
        $batch = $invitation->batch
            ? TeamInvitation::query()->where('batch', $invitation->batch)->whereRaw('lower(email) = ?', [strtolower($user->email)])->where('expires_at', '>', now())->get()
            : collect([$invitation]);

        if ($invitation->organization_id) {
            $club = Organization::findOrFail($invitation->organization_id);

            if (Organization::query()->whereHas('members', fn ($q) => $q->whereKey($user->id))->exists()) {
                return back()->withErrors(['invitation' => __('You already belong to a club.')]);
            }

            $club->addMember($user, Organization::roleFromTeamRole($invitation->role));
        } else {
            foreach ($batch as $item) {
                $item->team->members()->syncWithoutDetaching([$user->id => ['role' => $item->role]]);
            }
        }

        $batch->each->delete();
        $user->switchTeam($team);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :team.', ['team' => $team->name])]);

        return to_route('dashboard');
    }
}
