<?php

namespace App\Http\Controllers;

use App\Models\GamePlan;
use App\Support\CurrentSeason;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $team = $user->activeTeam();
        $today = $team->today();

        $upcoming = $user->activeTeam()->practices()
            ->where('is_template', false)
            ->whereDate('date', '>=', $today)
            ->withCount('segments')
            ->withSum('segments', 'duration')
            ->orderBy('date')
            ->first();

        $nextGame = $team->gamePlans()
            ->whereDate('date', '>=', $today)
            ->orderBy('date')
            ->first(['id', 'name', 'opponent', 'date', 'kickoff', 'location']);

        $played = CurrentSeason::constrain($request, $team->gamePlans(), 'date')
            ->whereNotNull('score_for')
            ->whereNotNull('score_against')
            ->get();

        $unscored = $team->gamePlans()
            ->whereDate('date', '<', $today)
            ->where(fn ($q) => $q->whereNull('score_for')->orWhereNull('score_against'))
            ->orderByDesc('date')
            ->limit(3)
            ->get(['id', 'name', 'opponent', 'date']);

        $unreviewed = $team->practices()
            ->where('is_template', false)
            ->whereDate('date', '<', $today)
            ->whereDoesntHave('attendees')
            ->orderByDesc('date')
            ->limit(3)
            ->get(['id', 'name', 'date']);

        $weekEnd = $today->copy()->addDays(6);
        $week = [
            'practices' => $team->practices()->where('is_template', false)
                ->whereBetween('date', [$today, $weekEnd])
                ->orderBy('date')->get(['id', 'name', 'date', 'start_time']),
            'games' => $team->gamePlans()
                ->whereBetween('date', [$today, $weekEnd])
                ->orderBy('date')->get(['id', 'name', 'opponent', 'date', 'kickoff']),
        ];

        return Inertia::render('Dashboard', [
            'nextGame' => $nextGame,
            'record' => [
                'wins' => $played->filter(fn (GamePlan $g) => $g->score_for > $g->score_against)->count(),
                'losses' => $played->filter(fn (GamePlan $g) => $g->score_for < $g->score_against)->count(),
                'ties' => $played->filter(fn (GamePlan $g) => $g->score_for === $g->score_against)->count(),
            ],
            'attention' => [
                'unscoredGames' => $unscored,
                'noAttendance' => $unreviewed,
            ],
            'week' => $week,
            'counts' => [
                'plays' => $user->activeTeam()->plays()->count(),
                'drills' => $user->activeTeam()->drills()->count(),
                'practices' => $user->activeTeam()->practices()->where('is_template', false)->count(),
            ],
            'recentPlays' => $user->activeTeam()->plays()->latest('updated_at')->limit(4)->get(['id', 'name', 'category', 'formation', 'diagram']),
            'upcomingPractice' => $upcoming,
            'announcement' => $user->activeTeam()->announcement,
        ]);
    }
}
