<?php

namespace App\Http\Controllers;

use App\Models\Drill;
use App\Models\Formation;
use App\Models\Play;
use App\Models\Practice;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CopyToTeamController extends Controller
{
    private const KINDS = ['play', 'drill', 'formation', 'practice'];

    /**
     * Copy one item from the active team into another team the person can edit.
     * The original is untouched; the copy starts fresh (no favourite, history or dates).
     */
    public function __invoke(Request $request, string $kind, int $id): RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        $user = $request->user();
        $data = $request->validate(['team_id' => ['required', 'integer']]);
        $activeId = $user->activeTeam()->id;

        $target = $user->teams()->whereKey($data['team_id'])->where('teams.id', '!=', $activeId)->firstOrFail();
        abort_if(($user->teamRoles()[$target->id] ?? Team::VIEWER) === Team::VIEWER, 403);

        match ($kind) {
            'play' => $this->copyPlay(Play::query()->where('team_id', $activeId)->findOrFail($id), $target, $user->id),
            'drill' => $this->copyDrill(Drill::query()->where('team_id', $activeId)->findOrFail($id), $target, $user->id),
            'formation' => $this->copyFormation(Formation::query()->where('team_id', $activeId)->findOrFail($id), $target, $user->id),
            'practice' => $this->copyPractice(Practice::query()->where('team_id', $activeId)->findOrFail($id), $target, $user->id),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Copied to :team.', ['team' => $target->name])]);

        return back();
    }

    private function copyPlay(Play $play, Team $target, int $userId): Play
    {
        $copy = $play->replicate(['playbook_id', 'parent_id', 'is_favorite', 'team_id', 'user_id']);
        $copy->forceFill(['team_id' => $target->id, 'user_id' => $userId, 'playbook_id' => $target->defaultPlaybook()->id]);
        $copy->save();

        return $copy;
    }

    private function copyDrill(Drill $drill, Team $target, int $userId): Drill
    {
        $copy = $drill->replicate(['team_id', 'user_id']);
        $copy->forceFill(['team_id' => $target->id, 'user_id' => $userId])->save();

        return $copy;
    }

    private function copyFormation(Formation $formation, Team $target, int $userId): Formation
    {
        $copy = $formation->replicate(['team_id', 'user_id']);
        $copy->forceFill(['team_id' => $target->id, 'user_id' => $userId])->save();

        return $copy;
    }

    /**
     * A practice comes with its drills and plays, so its segments still link to something on the new team.
     */
    private function copyPractice(Practice $practice, Team $target, int $userId): void
    {
        $copy = $practice->replicate(['team_id', 'user_id', 'date', 'review_notes']);
        $copy->forceFill(['team_id' => $target->id, 'user_id' => $userId, 'date' => null, 'review_notes' => null])->save();

        $drills = [];
        $plays = [];

        foreach ($practice->segments()->orderBy('sort_order')->get() as $segment) {
            $drill = $segment->drill_id ? Drill::query()->where('team_id', $practice->team_id)->find($segment->drill_id) : null;
            $play = $segment->play_id ? Play::query()->where('team_id', $practice->team_id)->find($segment->play_id) : null;

            $copy->segments()->create([
                'title' => $segment->title,
                'duration' => $segment->duration,
                'notes' => $segment->notes,
                'sort_order' => $segment->sort_order,
                'drill_id' => $drill ? ($drills[$drill->id] ??= $this->copyDrill($drill, $target, $userId)->id) : null,
                'play_id' => $play ? ($plays[$play->id] ??= $this->copyPlay($play, $target, $userId)->id) : null,
            ]);
        }
    }
}
