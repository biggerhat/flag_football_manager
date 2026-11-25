<?php

namespace App\Http\Controllers;

use App\Models\Drill;
use App\Models\Formation;
use App\Models\GamePlan;
use App\Models\Play;
use App\Models\Practice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RestoreController extends Controller
{
    private const MODELS = [
        'play' => Play::class,
        'drill' => Drill::class,
        'formation' => Formation::class,
        'practice' => Practice::class,
        'gameplan' => GamePlan::class,
    ];

    /**
     * Bring back something that was just deleted (the "Undo" on the deletion toast).
     */
    public function __invoke(Request $request, string $kind, int $id): RedirectResponse
    {
        $class = self::MODELS[$kind] ?? abort(404);

        $item = $class::onlyTrashed()
            ->where('team_id', $request->user()->activeTeam()->id)
            ->findOrFail($id);

        $item->restore();

        // A play whose playbook was deleted in the meantime goes to the default one.
        if ($item instanceof Play && ! $item->playbook()->exists()) {
            $item->update(['playbook_id' => $request->user()->activeTeam()->defaultPlaybook()->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restored.')]);

        return back();
    }
}
