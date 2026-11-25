<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Inertia\Inertia;
use Inertia\Response;

class FamilyPageController extends Controller
{
    /**
     * A no-login page for parents and players: announcement and upcoming schedule. No roster or plans.
     */
    public function __invoke(string $token): Response
    {
        $team = Team::where('calendar_token', $token)->firstOrFail();

        return Inertia::render('Family', [
            'team' => $team->name,
            'announcement' => $team->announcement,
            'feedUrl' => route('calendar.feed', $token),
            'practices' => $team->practices()->where('is_template', false)
                ->whereDate('date', '>=', $team->today())->orderBy('date')->limit(10)
                ->get(['name', 'date', 'start_time', 'location'])
                ->map(fn ($p) => [...$p->only(['name', 'start_time', 'location']), 'date' => $p->date?->toDateString()]),
            'games' => $team->gamePlans()
                ->whereDate('date', '>=', $team->today())->orderBy('date')->limit(10)
                ->get(['opponent', 'name', 'date', 'kickoff', 'location'])
                ->map(fn ($g) => [...$g->only(['opponent', 'name', 'kickoff', 'location']), 'date' => $g->date?->toDateString()]),
        ]);
    }
}
