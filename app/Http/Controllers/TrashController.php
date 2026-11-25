<?php

namespace App\Http\Controllers;

use App\Models\Drill;
use App\Models\Formation;
use App\Models\GamePlan;
use App\Models\Play;
use App\Models\Practice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    /**
     * Things deleted in the last 30 days, each with a restore button.
     */
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->activeTeam();
        $since = now()->subDays(30);

        $classes = [
            'play' => Play::class,
            'drill' => Drill::class,
            'formation' => Formation::class,
            'practice' => Practice::class,
            'gameplan' => GamePlan::class,
        ];

        $items = collect();

        foreach ($classes as $kind => $class) {
            foreach ($class::onlyTrashed()->where('team_id', $team->id)->where('deleted_at', '>=', $since)->get() as $model) {
                $items->push([
                    'kind' => $kind,
                    'id' => $model->id,
                    'name' => $model->name,
                    'deleted_at' => $model->deleted_at->toIso8601String(),
                ]);
            }
        }

        $items = $items->sortByDesc('deleted_at')->values();

        return Inertia::render('Trash', ['items' => $items]);
    }
}
