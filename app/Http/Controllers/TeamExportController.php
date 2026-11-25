<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamExportController extends Controller
{
    /**
     * Everything the active team holds, as one JSON file to keep as a backup.
     */
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canEdit(), 403);

        $team = $request->user()->activeTeam();

        $data = [
            'app' => 'football-manager',
            'version' => 2,
            'exported_at' => now()->toIso8601String(),
            'team' => $team->only(['name', 'announcement', 'timezone']),
            'playbooks' => $team->playbooks()->get()->makeHidden(['share_token']),
            'plays' => $team->plays()->get(),
            'formations' => $team->formations()->get(),
            'drills' => $team->drills()->get(),
            'practices' => $team->practices()->with('segments')->get(),
            'game_plans' => $team->gamePlans()->with(['calls', 'plays:id'])->get()
                ->map(fn ($plan) => [...$plan->makeHidden('plays')->toArray(), 'play_ids' => $plan->plays->pluck('id')->all()]),
            'players' => $team->players()->get(),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="'.Str::slug($team->name).'-backup-'.now()->format('Ymd').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
