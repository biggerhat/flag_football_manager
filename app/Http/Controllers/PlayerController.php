<?php

namespace App\Http\Controllers;

use App\Http\Requests\Players\PlayerRequest;
use App\Models\Player;
use App\Models\Practice;
use App\Models\Season;
use App\Support\CurrentSeason;
use App\Support\PositionNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->activeTeam();

        // Only practices where attendance was taken count towards the total.
        $season = CurrentSeason::for($request);

        $tracked = DB::table('attendances')
            ->join('practices', 'practices.id', '=', 'attendances.practice_id')
            ->where('practices.team_id', $team->id)
            ->when($season, fn ($q) => $season->constrain($q, 'practices.date'))
            ->distinct()
            ->count('attendances.practice_id');

        return Inertia::render('players/Index', [
            'players' => $team->players()->withCount(['practices' => fn (Builder $q) => $this->inSeason($q, $season)])->orderByDesc('active')->orderBy('name')->get(),
            'practiceCount' => $tracked,
            'positionNames' => PositionNames::for($request->user()),
        ]);
    }

    /**
     * @param  Builder<Practice>  $query
     * @return Builder<Practice>
     */
    private function inSeason(Builder $query, ?Season $season): Builder
    {
        return $season ? $season->constrain($query, 'practices.date') : $query;
    }

    public function store(PlayerRequest $request): RedirectResponse
    {
        $request->user()->activeTeam()->players()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player added.')]);

        return back();
    }

    public function update(PlayerRequest $request, Player $player): RedirectResponse
    {
        $this->ensureOwned($request, $player);

        $player->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player saved.')]);

        return back();
    }

    public function destroy(Request $request, Player $player): RedirectResponse
    {
        $this->ensureOwned($request, $player);

        $player->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player removed.')]);

        return back();
    }
}
