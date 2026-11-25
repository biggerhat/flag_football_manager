<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Support\CurrentSeason;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return list<array{id: int, name: string, role: string}>
     */
    private function teamList(Request $request): array
    {
        $user = $request->user();
        $roles = $user->teamRoles();

        $list = [];

        foreach ($user->teams()->orderBy('name')->get(['teams.id', 'teams.name']) as $team) {
            $list[] = ['id' => $team->id, 'name' => $team->name, 'role' => $roles[$team->id] ?? Team::VIEWER];
        }

        return $list;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'team' => fn () => $request->user() ? [
                'id' => $request->user()->activeTeam()->id,
                'name' => $request->user()->activeTeam()->name,
                'role' => $request->user()->role(),
                'canEdit' => $request->user()->canEdit(),
                'teams' => $this->teamList($request),
            ] : null,
            'seasons' => fn () => $request->user() ? [
                'current' => CurrentSeason::for($request)?->id,
                'list' => $request->user()->activeTeam()->seasons()->get(['id', 'name', 'starts_on', 'ends_on']),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
