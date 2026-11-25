<?php

namespace App\Http\Controllers;

use App\Http\Requests\GamePlans\GamePlanRequest;
use App\Models\GamePlan;
use App\Models\Play;
use App\Support\CurrentSeason;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GamePlanController extends Controller
{
    public function index(Request $request): Response
    {
        $plans = CurrentSeason::constrain($request, $request->user()->activeTeam()->gamePlans(), 'date')
            ->withCount('plays')
            ->orderByDesc('date')
            ->latest('updated_at')
            ->get()
            ->map(fn (GamePlan $plan) => [...$plan->toArray(), 'result' => $plan->result()]);

        $played = $plans->whereNotNull('result');

        return Inertia::render('gameplans/Index', [
            'gamePlans' => $plans,
            'record' => [
                'wins' => $played->where('result', 'W')->count(),
                'losses' => $played->where('result', 'L')->count(),
                'ties' => $played->where('result', 'T')->count(),
                'pointsFor' => (int) $played->sum('score_for'),
                'pointsAgainst' => (int) $played->sum('score_against'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('gameplans/Builder', [
            'gamePlan' => null,
            'defaultDate' => $request->date('date')?->toDateString(),
            'plays' => $this->library($request),
            'playbooks' => $request->user()->activeTeam()->playbooks()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(GamePlanRequest $request): RedirectResponse
    {
        $gamePlan = $request->user()->activeTeam()->gamePlans()->create($request->safe()->except('play_ids'));
        $this->syncPlays($gamePlan, $request->validated('play_ids'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Game plan saved.')]);

        return to_route('gameplans.edit', $gamePlan);
    }

    public function edit(Request $request, GamePlan $gameplan): Response
    {
        $this->ensureOwned($request, $gameplan);

        return Inertia::render('gameplans/Builder', [
            'gamePlan' => [...$gameplan->toArray(), 'play_ids' => $gameplan->plays()->pluck('plays.id')],
            'defaultDate' => null,
            'plays' => $this->library($request),
            'playbooks' => $request->user()->activeTeam()->playbooks()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(GamePlanRequest $request, GamePlan $gameplan): RedirectResponse
    {
        $this->ensureOwned($request, $gameplan);

        $gameplan->update($request->safe()->except('play_ids'));
        $this->syncPlays($gameplan, $request->validated('play_ids'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Game plan saved.')]);

        return to_route('gameplans.edit', $gameplan);
    }

    public function destroy(Request $request, GamePlan $gameplan): RedirectResponse
    {
        $this->ensureOwned($request, $gameplan);

        $gameplan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Game plan deleted.'), 'undo' => ['kind' => 'gameplan', 'id' => $gameplan->id]]);

        return to_route('gameplans.index');
    }

    /**
     * @param  array<int, int>  $playIds
     */
    private function syncPlays(GamePlan $gamePlan, array $playIds): void
    {
        $gamePlan->plays()->sync(
            collect(array_values($playIds))->mapWithKeys(fn ($id, $index) => [$id => ['sort_order' => $index]])->all(),
        );
    }

    /**
     * @return Collection<int, Play>
     */
    private function library(Request $request)
    {
        return $request->user()->activeTeam()->plays()->orderBy('name')->get(['id', 'playbook_id', 'name', 'category', 'formation', 'diagram']);
    }
}
