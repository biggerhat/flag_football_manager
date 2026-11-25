<?php

namespace App\Http\Controllers;

use App\Models\Practice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrintController extends Controller
{
    /**
     * Printable play sheets. `ids` is a comma separated list; order is preserved.
     */
    public function plays(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $order = array_flip($ids->all());

        $plays = $request->user()->activeTeam()->plays()->whereIn('id', $ids)->get()
            ->sortBy(fn ($play) => $order[$play->id])
            ->values();

        return Inertia::render('print/Plays', [
            'kind' => 'play',
            'plays' => $plays,
            'team' => $request->user()->activeTeam()->name,
            'lineup' => $request->user()->activeTeam()->lineup(),
        ]);
    }

    /**
     * Printable formation sheets, rendered by the same page as plays.
     */
    public function formations(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $order = array_flip($ids->all());

        $formations = $request->user()->activeTeam()->formations()->whereIn('id', $ids)->get()
            ->sortBy(fn ($formation) => $order[$formation->id])
            ->values()
            ->map(fn ($formation) => [
                'id' => $formation->id,
                'name' => $formation->name,
                'notes' => $formation->description,
                'diagram' => $formation->diagram,
            ]);

        return Inertia::render('print/Plays', [
            'kind' => 'formation',
            'plays' => $formations,
            'team' => $request->user()->activeTeam()->name,
            'lineup' => [],
        ]);
    }

    public function practice(Request $request, Practice $practice): Response
    {
        $this->ensureOwned($request, $practice);

        return Inertia::render('print/Practice', [
            'practice' => $practice->load(['segments.drill', 'segments.play']),
            'team' => $request->user()->activeTeam()->name,
        ]);
    }
}
