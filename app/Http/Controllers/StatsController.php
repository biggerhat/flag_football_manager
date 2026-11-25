<?php

namespace App\Http\Controllers;

use App\Models\PlayCall;
use App\Support\CurrentSeason;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->activeTeam();

        $season = CurrentSeason::for($request);

        $calls = PlayCall::query()
            ->where('team_id', $team->id)
            // Calls from a game that was deleted (in the Trash) no longer count.
            ->where(fn ($q) => $q->whereNull('game_plan_id')->orWhereHas('gamePlan'))
            ->when($season, fn ($q) => $q->whereHas('gamePlan', fn ($g) => $season->constrain($g, 'date')))
            ->with(['play:id,name,category', 'gamePlan:id,name,opponent,date', 'player:id,name,number'])
            ->get();

        $summary = fn (Collection $group): array => [
            'calls' => $group->count(),
            'yards' => (int) $group->sum('yards'),
            'touchdowns' => $group->where('result', 'touchdown')->count(),
            'success' => $group->whereIn('result', PlayCall::SUCCESSES)->count(),
        ];

        return Inertia::render('Stats', [
            'totals' => $summary($calls),
            'byDown' => collect([1, 2, 3, 4])->map(fn (int $down) => [
                'down' => $down,
                ...$summary($calls->where('down', $down)),
            ])->values(),
            'byPlay' => $calls->groupBy('play_id')
                ->map(fn (Collection $group) => [
                    'name' => $group->first()->play->name,
                    'category' => $group->first()->play->category,
                    ...$summary($group),
                ])
                ->sortByDesc('calls')->values()->take(15),
            'byPlayer' => $calls->whereNotNull('player_id')->groupBy('player_id')
                ->map(fn (Collection $group) => [
                    'name' => $group->first()->player->name,
                    'number' => $group->first()->player->number,
                    ...$summary($group),
                ])
                ->sortByDesc('calls')->values(),
            'byGame' => $calls->whereNotNull('game_plan_id')->groupBy('game_plan_id')
                ->map(fn (Collection $group) => [
                    'name' => $group->first()->gamePlan->opponent ?: $group->first()->gamePlan->name,
                    'date' => $group->first()->gamePlan->date?->toDateString(),
                    ...$summary($group),
                ])
                ->sortBy('date')->values(),
        ]);
    }
}
