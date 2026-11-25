<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeasonController extends Controller
{
    /**
     * Choose which season to look at (or all time). Only changes this person's view.
     */
    public function switch(Request $request): RedirectResponse
    {
        $id = $request->validate(['season_id' => ['nullable', 'integer']])['season_id'] ?? null;

        if ($id !== null) {
            abort_unless($request->user()->activeTeam()->seasons()->whereKey($id)->exists(), 404);
            $request->session()->put('season_id', $id);
        } else {
            $request->session()->forget('season_id');
        }

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->activeTeam();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $season = $team->seasons()->create($data);
        $request->session()->put('season_id', $season->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season added.')]);

        return back();
    }

    public function destroy(Request $request, int $season): RedirectResponse
    {
        $team = $request->user()->activeTeam();
        $team->seasons()->whereKey($season)->firstOrFail()->delete();

        if ((int) $request->session()->get('season_id') === $season) {
            $request->session()->forget('season_id');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season removed. Your games and practices are untouched.')]);

        return back();
    }
}
