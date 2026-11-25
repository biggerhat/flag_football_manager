<?php

namespace App\Http\Controllers;

use App\Models\GamePlan;
use App\Models\PlayCall;
use App\Support\PlayStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GameDayController extends Controller
{
    /**
     * The sideline view: the plan's plays in order, with one-tap result logging.
     */
    public function show(Request $request, GamePlan $gameplan): Response
    {
        $this->ensureOwned($request, $gameplan);

        $team = $request->user()->activeTeam();

        return Inertia::render('gameplans/GameDay', [
            'gamePlan' => $gameplan->only(['id', 'name', 'opponent', 'score_for', 'score_against', 'notes']),
            'players' => $team->players()->where('active', true)->orderBy('number')->orderBy('name')->get(['id', 'name', 'number']),
            'plays' => $gameplan->plays()->get(['plays.id', 'plays.name', 'plays.category', 'plays.diagram']),
            'calls' => $gameplan->calls()->with('play:id,name')->latest('id')->get()
                ->map(fn (PlayCall $call) => [
                    'id' => $call->id,
                    'play_id' => $call->play_id,
                    'play' => $call->play->name,
                    'result' => $call->result,
                    'yards' => $call->yards,
                    'down' => $call->down,
                    'note' => $call->note,
                    'player_id' => $call->player_id,
                ]),
            'stats' => PlayStats::forTeam($team),
            'results' => PlayCall::RESULTS,
        ]);
    }

    public function store(Request $request, GamePlan $gameplan): RedirectResponse
    {
        $this->ensureOwned($request, $gameplan);

        $data = $request->validate([
            'play_id' => ['required', Rule::exists('plays', 'id')->where('team_id', $gameplan->team_id)->whereNull('deleted_at')],
            'result' => ['required', Rule::in(PlayCall::RESULTS)],
            'yards' => ['nullable', 'integer', 'between:-99,99'],
            'down' => ['nullable', 'integer', 'between:1,4'],
            'player_id' => ['nullable', Rule::exists('players', 'id')->where('team_id', $gameplan->team_id)],
            'note' => ['nullable', 'string', 'max:120'],
            'client_key' => ['nullable', 'string', 'max:64'],
        ]);

        // A retry of a call that already arrived (the reply was lost) is acknowledged, not logged again.
        if (isset($data['client_key']) && $gameplan->calls()->where('client_key', $data['client_key'])->exists()) {
            return back();
        }

        $call = new PlayCall($data);
        $call->team_id = $gameplan->team_id;
        $call->game_plan_id = $gameplan->id;
        $call->save();

        return back();
    }

    public function destroy(Request $request, GamePlan $gameplan, PlayCall $call): RedirectResponse
    {
        $this->ensureOwned($request, $gameplan);
        abort_unless($call->game_plan_id === $gameplan->id, 404);

        $call->delete();

        return back();
    }

    public function score(Request $request, GamePlan $gameplan): RedirectResponse
    {
        $this->ensureOwned($request, $gameplan);

        $gameplan->update($request->validate([
            'score_for' => ['nullable', 'integer', 'between:0,999'],
            'score_against' => ['nullable', 'integer', 'between:0,999'],
        ]));

        return back();
    }
}
