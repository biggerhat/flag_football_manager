<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CalendarFeedController extends Controller
{
    /**
     * The subscribable .ics feed, found by its secret token.
     */
    public function show(string $token): Response
    {
        $team = Team::where('calendar_token', $token)->firstOrFail();
        $from = $team->today()->subDays(30)->toDateString();

        $events = [];

        foreach ($team->practices()->where('is_template', false)->whereNotNull('date')->whereDate('date', '>=', $from)->get() as $practice) {
            $start = $practice->start_time
                ? Carbon::parse($practice->date->toDateString().' '.$practice->start_time)
                : null;

            $events[] = $this->event("practice-{$practice->id}", 'Practice: '.$practice->name, $practice->date, $start, $practice->duration ?: 60, $practice->location, $practice->objectives);
        }

        foreach ($team->gamePlans()->whereNotNull('date')->whereDate('date', '>=', $from)->get() as $game) {
            $start = $game->kickoff
                ? Carbon::parse($game->date->toDateString().' '.$game->kickoff)
                : null;
            $title = $game->opponent ? "Game vs {$game->opponent}" : "Game: {$game->name}";

            $events[] = $this->event("game-{$game->id}", $title, $game->date, $start, 120, $game->location, null);
        }

        $body = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Football Manager//Team calendar//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->escape($team->name),
            ...$events,
            'END:VCALENDAR',
        ])."\r\n";

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="team.ics"',
        ]);
    }

    /**
     * Create the team's feed link, or replace it (which stops the old link working).
     */
    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->activeTeam();
        $team->forceFill(['calendar_token' => Str::random(40)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar link ready.')]);

        return back();
    }

    private function event(string $uid, string $title, CarbonInterface $date, ?CarbonInterface $start, int $minutes, ?string $location, ?string $notes): string
    {
        // Times are "floating" (no timezone), so 5:30 stays 5:30 wherever the phone is.
        $lines = ['BEGIN:VEVENT', "UID:{$uid}@football-manager", 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'), 'SUMMARY:'.$this->escape($title)];

        if ($start) {
            $lines[] = 'DTSTART:'.$start->format('Ymd\THis');
            $lines[] = 'DTEND:'.$start->addMinutes($minutes)->format('Ymd\THis');
        } else {
            $lines[] = 'DTSTART;VALUE=DATE:'.$date->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$date->addDay()->format('Ymd');
        }

        if ($location) {
            $lines[] = 'LOCATION:'.$this->escape($location);
        }

        if ($notes) {
            $lines[] = 'DESCRIPTION:'.$this->escape($notes);
        }

        $lines[] = 'END:VEVENT';

        return implode("\r\n", $lines);
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }
}
