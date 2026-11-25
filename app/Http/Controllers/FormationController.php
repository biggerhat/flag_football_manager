<?php

namespace App\Http\Controllers;

use App\Http\Requests\Formations\FormationRequest;
use App\Models\Formation;
use App\Support\PositionNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('formations/Index', [
            'formations' => $request->user()->activeTeam()->formations()->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('formations/Editor', [
            'formation' => null,
            'positionNames' => PositionNames::for($request->user()),
        ]);
    }

    public function store(FormationRequest $request): RedirectResponse
    {
        $formation = $request->user()->activeTeam()->formations()->create($request->formation());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Formation saved.')]);

        return $request->boolean('stay') ? back() : to_route('formations.edit', $formation);
    }

    public function edit(Request $request, Formation $formation): Response
    {
        $this->ensureOwned($request, $formation);

        return Inertia::render('formations/Editor', [
            'formation' => $formation,
            'positionNames' => PositionNames::for($request->user()),
        ]);
    }

    public function update(FormationRequest $request, Formation $formation): RedirectResponse
    {
        $this->ensureOwned($request, $formation);

        $formation->update($request->formation());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Formation saved.')]);

        return to_route('formations.edit', $formation);
    }

    public function duplicate(Request $request, Formation $formation): RedirectResponse
    {
        $this->ensureOwned($request, $formation);

        $copy = $formation->replicate();
        $copy->name = $formation->name.' (copy)';
        $copy->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Formation duplicated.')]);

        return to_route('formations.edit', $copy);
    }

    public function destroy(Request $request, Formation $formation): RedirectResponse
    {
        $this->ensureOwned($request, $formation);

        $formation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Formation deleted.'), 'undo' => ['kind' => 'formation', 'id' => $formation->id]]);

        return to_route('formations.index');
    }
}
