<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const LIMIT = 6;

    /**
     * One search across everything the active team owns. Returns links, grouped by kind.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $team = $request->user()->activeTeam();

        $groups = [
            ['label' => 'Playbooks', 'items' => $team->playbooks()->where('name', 'like', $like)->limit(self::LIMIT)->get(['id', 'name'])
                ->map(fn ($m) => ['title' => $m->name, 'href' => route('playbooks.show', $m->id)])],
            ['label' => 'Plays', 'items' => $team->plays()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('formation', 'like', $like)->orWhere('folder', 'like', $like)->orWhere('tags', 'like', $like))
                ->limit(self::LIMIT)->get(['id', 'name', 'formation'])
                ->map(fn ($m) => ['title' => $m->name, 'subtitle' => $m->formation, 'href' => route('plays.edit', $m->id)])],
            ['label' => 'Formations', 'items' => $team->formations()->where('name', 'like', $like)->limit(self::LIMIT)->get(['id', 'name'])
                ->map(fn ($m) => ['title' => $m->name, 'href' => route('formations.edit', $m->id)])],
            ['label' => 'Drills', 'items' => $team->drills()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('category', 'like', $like))->limit(self::LIMIT)->get(['id', 'name', 'category'])
                ->map(fn ($m) => ['title' => $m->name, 'subtitle' => $m->category, 'href' => route('drills.edit', $m->id)])],
            ['label' => 'Practices', 'items' => $team->practices()->where('name', 'like', $like)->latest('date')->limit(self::LIMIT)->get(['id', 'name', 'date'])
                ->map(fn ($m) => ['title' => $m->name, 'subtitle' => $m->date?->toDateString(), 'href' => route('practices.edit', $m->id)])],
            ['label' => 'Game plans', 'items' => $team->gamePlans()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('opponent', 'like', $like))->limit(self::LIMIT)->get(['id', 'name', 'opponent'])
                ->map(fn ($m) => ['title' => $m->name, 'subtitle' => $m->opponent, 'href' => route('gameplans.edit', $m->id)])],
            ['label' => 'Players', 'items' => $team->players()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('number', $term))->limit(self::LIMIT)->get(['id', 'name', 'number'])
                ->map(fn ($m) => ['title' => $m->name, 'subtitle' => $m->number ? "#{$m->number}" : null, 'href' => route('players.index')])],
        ];

        return response()->json([
            'groups' => collect($groups)->filter(fn ($group) => $group['items']->isNotEmpty())->values(),
        ]);
    }
}
