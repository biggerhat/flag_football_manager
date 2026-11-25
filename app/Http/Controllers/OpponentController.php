<?php

namespace App\Http\Controllers;

use App\Models\GamePlan;
use App\Models\Opponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpponentController extends Controller
{
    /**
     * Every team you have a game plan against, with the head-to-head record and your scouting notes.
     */
    public function index(Request $request): Response
    {
        $team = $request->user()->activeTeam();

        $notes = Opponent::where('team_id', $team->id)->get()->keyBy(fn (Opponent $o) => mb_strtolower($o->name));

        $opponents = $team->gamePlans()
            ->whereNotNull('opponent')->where('opponent', '!=', '')
            ->orderByDesc('date')
            ->get()
            ->groupBy(fn (GamePlan $plan) => mb_strtolower(trim($plan->opponent)))
            ->map(function ($plans, string $key) use ($notes) {
                $played = $plans->filter(fn (GamePlan $p) => $p->result() !== null);

                return [
                    'name' => trim($plans->first()->opponent),
                    'notes' => $notes->get($key)?->notes,
                    'wins' => $played->filter(fn (GamePlan $p) => $p->result() === 'W')->count(),
                    'losses' => $played->filter(fn (GamePlan $p) => $p->result() === 'L')->count(),
                    'ties' => $played->filter(fn (GamePlan $p) => $p->result() === 'T')->count(),
                    'pointsFor' => (int) $played->sum('score_for'),
                    'pointsAgainst' => (int) $played->sum('score_against'),
                    'games' => $plans->map(fn (GamePlan $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'date' => $p->date?->toDateString(),
                        'result' => $p->result(),
                        'score_for' => $p->score_for,
                        'score_against' => $p->score_against,
                    ])->values(),
                ];
            })
            ->sortBy(fn (array $o) => mb_strtolower($o['name']))
            ->values();

        return Inertia::render('Opponents', ['opponents' => $opponents]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $team = $request->user()->activeTeam();
        $name = trim($data['name']);

        $existing = Opponent::where('team_id', $team->id)->get()
            ->first(fn (Opponent $o) => mb_strtolower($o->name) === mb_strtolower($name));

        $opponent = $existing ?? new Opponent(['name' => $name]);
        $opponent->team_id = $team->id;
        $opponent->notes = $data['notes'] ?: null;
        $opponent->save();

        return back();
    }
}
