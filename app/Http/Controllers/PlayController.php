<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plays\PlayRequest;
use App\Models\Formation;
use App\Models\Play;
use App\Models\Playbook;
use App\Models\PlayVersion;
use App\Support\PositionNames;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlayController extends Controller
{
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('plays/Editor', [
            'play' => null,
            'playbooks' => $this->playbooks($request),
            'playbookId' => $user->activeTeam()->playbooks()->whereKey($request->integer('playbook'))->value('id')
                ?? $user->activeTeam()->defaultPlaybook()->id,
            'folders' => $this->folders($request),
            'positionNames' => PositionNames::for($request->user()),
            'formations' => $this->formations($request),
        ]);
    }

    public function store(PlayRequest $request): RedirectResponse
    {
        $play = $request->user()->activeTeam()->plays()->create($this->attributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Play saved.')]);

        return to_route('plays.edit', $play);
    }

    public function edit(Request $request, Play $play): Response
    {
        $this->ensureOwned($request, $play);

        return Inertia::render('plays/Editor', [
            'play' => $play,
            'playbooks' => $this->playbooks($request),
            'playbookId' => $play->playbook_id,
            'folders' => $this->folders($request),
            'positionNames' => PositionNames::for($request->user()),
            'formations' => $this->formations($request),
        ]);
    }

    public function update(PlayRequest $request, Play $play): RedirectResponse
    {
        $this->ensureOwned($request, $play);

        $play->fill($this->attributes($request));
        $play->recordVersionIfChanged($request->user()->id);
        $play->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Play saved.')]);

        return to_route('plays.edit', $play);
    }

    /**
     * Earlier saved versions of a play, newest first.
     */
    public function versions(Request $request, Play $play): JsonResponse
    {
        $this->ensureOwned($request, $play);

        return response()->json($play->versions()->with('author:id,name')->get()->map(fn (PlayVersion $v) => [
            'id' => $v->id,
            'name' => $v->name,
            'diagram' => $v->diagram,
            'author' => $v->author?->name,
            'saved_at' => $v->created_at->toIso8601String(),
        ]));
    }

    /**
     * Put an earlier version back. The current state is kept as a version first, so this can be undone.
     */
    public function restoreVersion(Request $request, Play $play, PlayVersion $version): RedirectResponse
    {
        $this->ensureOwned($request, $play);
        abort_unless($version->play_id === $play->id, 404);

        $play->fill(['name' => $version->name, 'notes' => $version->notes, 'diagram' => $version->diagram]);
        $play->recordVersionIfChanged($request->user()->id);
        $play->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Earlier version restored.')]);

        return to_route('plays.edit', $play);
    }

    public function favorite(Request $request, Play $play): RedirectResponse
    {
        $this->ensureOwned($request, $play);

        $play->forceFill(['is_favorite' => ! $play->is_favorite])->save();

        return back();
    }

    public function duplicate(Request $request, Play $play): RedirectResponse
    {
        $this->ensureOwned($request, $play);

        $copy = $play->replicate();
        $copy->name = $play->name.' (copy)';
        $copy->is_favorite = false;
        $copy->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Play duplicated.')]);

        return to_route('plays.edit', $copy);
    }

    /**
     * A copy kept alongside its original, so one play can have several versions.
     */
    public function variation(Request $request, Play $play): RedirectResponse
    {
        $this->ensureOwned($request, $play);

        $copy = $play->replicate();
        $copy->name = $play->name.' (variation)';
        $copy->parent_id = $play->parent_id ?? $play->id;
        $copy->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variation created.')]);

        return to_route('plays.edit', $copy);
    }

    public function destroy(Request $request, Play $play): RedirectResponse
    {
        $this->ensureOwned($request, $play);

        $play->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Play deleted.'), 'undo' => ['kind' => 'play', 'id' => $play->id]]);

        return to_route('playbooks.show', $play->playbook_id);
    }

    /**
     * Move several plays into another playbook.
     */
    public function move(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'playbook_id' => ['required', Rule::exists('playbooks', 'id')->where('team_id', $user->activeTeam()->id)],
        ]);

        $moved = $user->activeTeam()->plays()->whereIn('id', $data['ids'])->update(['playbook_id' => $data['playbook_id']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count play moved.|:count plays moved.', $moved)]);

        return back();
    }

    /**
     * Star, unstar or tag several plays at once.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['star', 'unstar', 'tag', 'delete'])],
            'tag' => ['required_if:action,tag', 'nullable', 'string', 'max:30'],
        ]);

        $plays = $request->user()->activeTeam()->plays()->whereIn('id', $data['ids'])->get();

        foreach ($plays as $play) {
            if ($data['action'] === 'delete') {
                $play->delete();
            } elseif ($data['action'] === 'tag') {
                $tags = $play->tags ?? [];
                $tag = trim($data['tag']);
                $play->tags = in_array($tag, $tags, true) ? $tags : [...$tags, $tag];
                $play->save();
            } else {
                $play->forceFill(['is_favorite' => $data['action'] === 'star'])->save();
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['action'] === 'delete'
            ? trans_choice(':count play deleted. You can restore it from Recently deleted.|:count plays deleted. You can restore them from Recently deleted.', $plays->count())
            : trans_choice(':count play updated.|:count plays updated.', $plays->count())]);

        return back();
    }

    /**
     * Validated play fields; an omitted playbook leaves the play where it is.
     *
     * @return array<string, mixed>
     */
    private function attributes(PlayRequest $request): array
    {
        return array_filter($request->validated(), fn ($value, $key) => $key !== 'playbook_id' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return Collection<int, Playbook>
     */
    private function playbooks(Request $request): Collection
    {
        return $request->user()->activeTeam()->playbooks()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function folders(Request $request)
    {
        return $request->user()->activeTeam()->plays()->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder');
    }

    /**
     * @return Collection<int, Formation>
     */
    private function formations(Request $request): Collection
    {
        return $request->user()->activeTeam()->formations()->orderBy('name')->get(['id', 'name', 'diagram']);
    }
}
