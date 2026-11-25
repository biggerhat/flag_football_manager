<?php

namespace App\Http\Controllers;

use App\Http\Requests\Practices\PracticeRequest;
use App\Models\Practice;
use App\Support\UsageDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('practices/Index', [
            'practices' => $request->user()->activeTeam()->practices()
                ->withCount('segments')
                ->withSum('segments', 'duration')
                ->orderByDesc('date')
                ->latest('updated_at')
                ->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('practices/Builder', [
            'practice' => null,
            'defaultDate' => $request->date('date')?->toDateString(),
            'present' => [],
            ...$this->library($request),
        ]);
    }

    public function store(PracticeRequest $request): RedirectResponse
    {
        $practice = $request->user()->activeTeam()->practices()->create($request->safe()->except('segments', 'present'));
        $this->syncSegments($practice, $request->validated('segments'));
        $practice->attendees()->sync($request->validated('present') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Practice saved.')]);

        return to_route('practices.edit', $practice);
    }

    public function edit(Request $request, Practice $practice): Response
    {
        $this->ensureOwned($request, $practice);

        return Inertia::render('practices/Builder', [
            'practice' => $practice->load('segments'),
            'defaultDate' => null,
            'present' => $practice->attendees()->pluck('players.id'),
            ...$this->library($request),
        ]);
    }

    public function run(Request $request, Practice $practice): Response
    {
        $this->ensureOwned($request, $practice);

        return Inertia::render('practices/Run', [
            'practice' => $practice->load('segments:id,practice_id,title,duration,notes,sort_order'),
        ]);
    }

    public function update(PracticeRequest $request, Practice $practice): RedirectResponse
    {
        $this->ensureOwned($request, $practice);

        $practice->update($request->safe()->except('segments', 'present'));
        $this->syncSegments($practice, $request->validated('segments'));
        $practice->attendees()->sync($request->validated('present') ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Practice saved.')]);

        return to_route('practices.edit', $practice);
    }

    /**
     * Copy a practice, optionally as a reusable template. Segments are copied, not linked.
     */
    public function duplicate(Request $request, Practice $practice): RedirectResponse
    {
        $this->ensureOwned($request, $practice);

        $asTemplate = $request->boolean('template');

        $copy = $practice->replicate();
        $baseName = preg_replace('/ \(template\)$/', '', $practice->name) ?? $practice->name;
        $copy->name = match (true) {
            $asTemplate => $baseName.' (template)',
            $practice->is_template => $baseName,
            default => $baseName.' (copy)',
        };
        $copy->review_notes = null;
        $copy->is_template = $asTemplate;
        // Scheduling from a template (the calendar) puts the copy on the chosen day.
        $copy->date = $asTemplate ? null : ($request->date('date') ?? $practice->date);
        $copy->save();

        foreach ($practice->segments as $segment) {
            $copy->segments()->create($segment->only(['drill_id', 'play_id', 'title', 'duration', 'notes', 'sort_order']));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $asTemplate ? __('Saved as template.') : __('Practice duplicated.')]);

        return to_route('practices.edit', $copy);
    }

    public function destroy(Request $request, Practice $practice): RedirectResponse
    {
        $this->ensureOwned($request, $practice);

        $practice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Practice deleted.'), 'undo' => ['kind' => 'practice', 'id' => $practice->id]]);

        return to_route('practices.index');
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function syncSegments(Practice $practice, array $segments): void
    {
        $practice->segments()->delete();

        foreach (array_values($segments) as $index => $segment) {
            $practice->segments()->create([...$segment, 'sort_order' => $index]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function library(Request $request): array
    {
        $user = $request->user();

        $lastRun = UsageDates::drills($user);

        return [
            'roster' => $user->activeTeam()->players()->where('active', true)->orderBy('name')->get(['id', 'name', 'number']),
            'drills' => $user->activeTeam()->drills()->orderBy('category')->orderBy('name')->get(['id', 'name', 'category', 'duration'])
                ->map(fn ($drill) => [...$drill->toArray(), 'last_run' => $lastRun[$drill->id] ?? null]),
            'plays' => $user->activeTeam()->plays()->orderBy('name')->get(['id', 'name', 'category', 'formation', 'diagram']),
        ];
    }
}
