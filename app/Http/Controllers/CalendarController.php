<?php

namespace App\Http\Controllers;

use App\Models\GamePlan;
use App\Models\Practice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * A month of practices and games.
     */
    public function __invoke(Request $request): Response
    {
        $team = $request->user()->activeTeam();

        try {
            $month = Carbon::createFromFormat('!Y-m', (string) $request->query('month', $team->today()->format('Y-m')));
        } catch (\Throwable) {
            $month = null;
        }

        $month = ($month ?: $team->today())->startOfMonth();

        return Inertia::render('calendar/Index', [
            'feedUrl' => $team->calendar_token ? route('calendar.feed', $team->calendar_token) : null,
            'familyUrl' => $team->calendar_token ? route('family', $team->calendar_token) : null,
            'month' => $month->format('Y-m'),
            'today' => $team->today()->toDateString(),
            'practices' => $team->practices()->where('is_template', false)
                ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->orderBy('start_time')
                ->get(['id', 'name', 'date', 'start_time', 'location'])
                ->map(fn (Practice $practice) => [...$practice->only(['id', 'name', 'start_time', 'location']), 'date' => $practice->date?->toDateString()]),
            'games' => $team->gamePlans()
                ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->orderBy('kickoff')
                ->get()
                ->map(fn (GamePlan $plan) => [
                    ...$plan->only(['id', 'name', 'opponent', 'kickoff', 'location', 'score_for', 'score_against']),
                    'date' => $plan->date?->toDateString(),
                    'result' => $plan->result(),
                ]),
            'templates' => $team->practices()->where('is_template', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
