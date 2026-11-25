<?php

namespace App\Http\Controllers;

use App\Models\GamePlan;
use App\Models\Practice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamOverviewController extends Controller
{
    /**
     * Every team the person belongs to, side by side: record, what is next, and a switch button.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $active = $user->activeTeam();
        $roles = $user->teamRoles();

        $teams = $user->teams()->withCount('members')->with('organization:id,name')->orderBy('name')->get();
        $plans = GamePlan::query()->whereIn('team_id', $teams->pluck('id'))->get(['id', 'team_id', 'name', 'opponent', 'date', 'kickoff', 'score_for', 'score_against'])->groupBy('team_id');
        $practices = Practice::query()->whereIn('team_id', $teams->pluck('id'))->where('is_template', false)->get(['id', 'team_id', 'name', 'date', 'start_time'])->groupBy('team_id');

        return Inertia::render('teams/Index', [
            'teams' => $teams->map(function ($team) use ($plans, $practices, $active, $roles) {
                $today = $team->today();
                $games = $plans->get($team->id, collect());
                $played = $games->filter(fn ($g) => $g->score_for !== null && $g->score_against !== null);
                $nextGame = $games->filter(fn ($g) => $g->date && $g->date->gte($today))->sortBy('date')->first();
                $nextPractice = $practices->get($team->id, collect())->filter(fn ($p) => $p->date && $p->date->gte($today))->sortBy('date')->first();

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'role' => $roles[$team->id] ?? 'viewer',
                    'members' => $team->members_count,
                    'active' => $team->id === $active->id,
                    'club' => $team->organization?->name,
                    'record' => [
                        'wins' => $played->filter(fn ($g) => $g->score_for > $g->score_against)->count(),
                        'losses' => $played->filter(fn ($g) => $g->score_for < $g->score_against)->count(),
                        'ties' => $played->filter(fn ($g) => $g->score_for === $g->score_against)->count(),
                    ],
                    'nextGame' => $nextGame ? ['name' => $nextGame->opponent ? "vs {$nextGame->opponent}" : $nextGame->name, 'date' => $nextGame->date?->toDateString()] : null,
                    'nextPractice' => $nextPractice ? ['name' => $nextPractice->name, 'date' => $nextPractice->date?->toDateString()] : null,
                ];
            })->values(),
        ]);
    }
}
