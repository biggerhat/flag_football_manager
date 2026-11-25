<?php

namespace App\Http\Controllers;

use App\Models\GamePlan;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamImportController extends Controller
{
    /**
     * Add everything from a backup file to the active team. Nothing is overwritten:
     * every item is created fresh and the links between them are rewired.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canEdit(), 403);

        $request->validate(['file' => ['required', 'file', 'max:20480']]);

        $data = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);

        if (! is_array($data) || ($data['app'] ?? null) !== 'football-manager' || ! in_array($data['version'] ?? 0, [1, 2], true)) {
            return back()->withErrors(['file' => __('That is not a backup from this app.')]);
        }

        $team = $request->user()->activeTeam();
        $counts = DB::transaction(fn () => $this->restore($team, $data));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imported :plays plays, :drills drills, :practices practices and :plans game plans.', $counts)]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{plays: int, drills: int, practices: int, plans: int}
     */
    private function restore(Team $team, array $data): array
    {
        $rows = fn (string $key): array => array_values(array_filter((array) ($data[$key] ?? []), 'is_array'));

        $books = [];
        foreach ($rows('playbooks') as $row) {
            $books[$row['id'] ?? null] = $team->playbooks()->create(Arr::only($row, ['name', 'description']))->id;
        }
        $fallbackBook = $team->defaultPlaybook()->id;

        $plays = [];
        $parents = [];
        foreach ($rows('plays') as $row) {
            $play = $team->plays()->create([...Arr::only($row, ['name', 'formation', 'category', 'difficulty', 'beginner_friendly', 'tags', 'folder', 'notes', 'diagram']), 'playbook_id' => $books[$row['playbook_id'] ?? null] ?? $fallbackBook]);
            $play->forceFill(['is_favorite' => (bool) ($row['is_favorite'] ?? false)])->save();
            $plays[$row['id'] ?? null] = $play->id;
            if (! empty($row['parent_id'])) {
                $parents[$play->id] = $row['parent_id'];
            }
        }
        foreach ($parents as $newId => $oldParent) {
            if (isset($plays[$oldParent])) {
                $team->plays()->whereKey($newId)->update(['parent_id' => $plays[$oldParent]]);
            }
        }

        foreach ($rows('formations') as $row) {
            $team->formations()->create(Arr::only($row, ['name', 'description', 'diagram']));
        }

        $drills = [];
        foreach ($rows('drills') as $row) {
            $drills[$row['id'] ?? null] = $team->drills()->create(Arr::only($row, ['name', 'category', 'duration', 'players', 'equipment', 'instructions', 'coaching_points', 'diagram']))->id;
        }

        $practices = $rows('practices');
        foreach ($practices as $row) {
            $practice = $team->practices()->create(Arr::only($row, ['name', 'date', 'start_time', 'duration', 'location', 'objectives', 'notes', 'review_notes', 'is_template']));
            foreach (array_filter((array) ($row['segments'] ?? []), 'is_array') as $segment) {
                $practice->segments()->create([...Arr::only($segment, ['title', 'duration', 'notes', 'sort_order']), 'drill_id' => $drills[$segment['drill_id'] ?? null] ?? null, 'play_id' => $plays[$segment['play_id'] ?? null] ?? null]);
            }
        }

        $players = [];
        foreach ($rows('players') as $row) {
            $players[$row['id'] ?? null] = $team->players()->create(Arr::only($row, ['name', 'number', 'position', 'notes', 'active']))->id;
        }

        $plans = $rows('game_plans');
        foreach ($plans as $row) {
            /** @var GamePlan $plan */
            $plan = $team->gamePlans()->create(Arr::only($row, ['name', 'opponent', 'date', 'notes', 'location', 'kickoff', 'score_for', 'score_against']));
            $order = collect((array) ($row['play_ids'] ?? []))->map(fn ($id) => $plays[$id] ?? null)->filter()->values();
            $plan->plays()->sync($order->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());

            foreach (array_filter((array) ($row['calls'] ?? []), 'is_array') as $call) {
                if (! isset($plays[$call['play_id'] ?? null])) {
                    continue;
                }
                $new = $plan->calls()->make([...Arr::only($call, ['result', 'yards', 'down', 'note']), 'play_id' => $plays[$call['play_id']], 'player_id' => $players[$call['player_id'] ?? null] ?? null]);
                $new->team_id = $team->id;
                $new->save();
            }
        }

        return ['plays' => count($plays), 'drills' => count($drills), 'practices' => count($practices), 'plans' => count($plans)];
    }
}
