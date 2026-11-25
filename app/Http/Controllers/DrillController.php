<?php

namespace App\Http\Controllers;

use App\Http\Requests\Drills\DrillRequest;
use App\Models\Drill;
use App\Support\UsageDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DrillController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $lastRun = UsageDates::drills($user);

        return Inertia::render('drills/Index', [
            'drills' => $user->activeTeam()->drills()->orderBy('category')->orderBy('name')->get()
                ->map(fn (Drill $drill) => [...$drill->toArray(), 'last_run' => $lastRun[$drill->id] ?? null]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('drills/Form', ['drill' => null]);
    }

    public function store(DrillRequest $request): RedirectResponse
    {
        $request->user()->activeTeam()->drills()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Drill saved.')]);

        return to_route('drills.index');
    }

    public function edit(Request $request, Drill $drill): Response
    {
        $this->ensureOwned($request, $drill);

        return Inertia::render('drills/Form', ['drill' => $drill]);
    }

    public function update(DrillRequest $request, Drill $drill): RedirectResponse
    {
        $this->ensureOwned($request, $drill);

        $drill->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Drill saved.')]);

        return to_route('drills.index');
    }

    public function destroy(Request $request, Drill $drill): RedirectResponse
    {
        $this->ensureOwned($request, $drill);

        $drill->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Drill deleted.'), 'undo' => ['kind' => 'drill', 'id' => $drill->id]]);

        return to_route('drills.index');
    }
}
