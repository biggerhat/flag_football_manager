<?php

namespace App\Http\Controllers;

use App\Http\Requests\Playbooks\PlaybookRequest;
use App\Models\Play;
use App\Models\Playbook;
use App\Support\PlayStats;
use App\Support\UsageDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlaybookController extends Controller
{
    /**
     * Every playbook, each with a few plays as a preview.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->activeTeam()->defaultPlaybook();

        $playbooks = $user->activeTeam()->playbooks()->withCount('plays')->orderBy('name')->get();

        // The three most recent plays of every playbook in one query instead of one per playbook.
        $ranked = Play::query()
            ->whereIn('playbook_id', $playbooks->pluck('id'))
            ->selectRaw('plays.id, plays.playbook_id, plays.diagram, row_number() over (partition by plays.playbook_id order by plays.updated_at desc, plays.id desc) as rn');
        $previews = Play::withoutGlobalScopes()->fromSub($ranked, 'plays')->where('rn', '<=', 3)->get()->groupBy('playbook_id');

        return Inertia::render('playbooks/Index', [
            'playbooks' => $playbooks->map(fn (Playbook $playbook) => [
                ...$playbook->toArray(),
                'previews' => ($previews[$playbook->id] ?? collect())->map(fn ($play) => ['id' => $play->id, 'diagram' => $play->diagram])->values(),
            ]),
        ]);
    }

    public function store(PlaybookRequest $request): RedirectResponse
    {
        $playbook = $request->user()->activeTeam()->playbooks()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playbook created.')]);

        return to_route('playbooks.show', $playbook);
    }

    /**
     * The plays in one playbook.
     */
    public function show(Request $request, Playbook $playbook): Response
    {
        $this->ensureOwned($request, $playbook);

        $user = $request->user();
        $lastRun = UsageDates::plays($user);
        $stats = PlayStats::forTeam($user->activeTeam());

        return Inertia::render('playbooks/Show', [
            'playbook' => [...$playbook->toArray(), 'share_url' => $playbook->shareIsLive() ? route('share.show', $playbook->share_token) : null, 'share_expires_at' => $playbook->shareIsLive() ? $playbook->share_expires_at?->toDateString() : null],
            'playbooks' => $user->activeTeam()->playbooks()->orderBy('name')->get(['id', 'name']),
            'plays' => $playbook->plays()->latest('updated_at')->get()
                ->map(fn (Play $play) => [...$play->toArray(), 'last_run' => $lastRun[$play->id] ?? null, 'stats' => $stats[$play->id] ?? null]),
        ]);
    }

    /**
     * A printable packet: cover page, contents and every play.
     */
    public function packet(Request $request, Playbook $playbook): Response
    {
        $this->ensureOwned($request, $playbook);

        return Inertia::render('playbooks/Packet', [
            ...$playbook->packet(),
            'shareUrl' => $playbook->shareIsLive() ? route('share.show', $playbook->share_token) : null,
            'public' => false,
        ]);
    }

    /**
     * Turn the read-only share link on (or replace it with a new one), or off.
     */
    public function share(Request $request, Playbook $playbook): RedirectResponse
    {
        $this->ensureOwned($request, $playbook);

        $enable = $request->boolean('enabled', true);
        $playbook->forceFill([
            'share_token' => $enable ? Str::random(40) : null,
            'share_expires_at' => $enable ? now()->addDays(90) : null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $enable ? __('Share link ready.') : __('Sharing turned off.')]);

        return back();
    }

    public function update(PlaybookRequest $request, Playbook $playbook): RedirectResponse
    {
        $this->ensureOwned($request, $playbook);

        $playbook->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playbook saved.')]);

        return back();
    }

    /**
     * Only an empty playbook can be deleted, so plays are never lost by accident.
     */
    public function destroy(Request $request, Playbook $playbook): RedirectResponse
    {
        $this->ensureOwned($request, $playbook);

        if ($playbook->plays()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Move or delete this playbook\'s plays first.')]);

            return back();
        }

        $playbook->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Playbook deleted.')]);

        return to_route('playbooks.index');
    }
}
