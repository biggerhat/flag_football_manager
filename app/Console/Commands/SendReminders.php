<?php

namespace App\Console\Commands;

use App\Mail\EventReminder;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendReminders extends Command
{
    protected $signature = 'reminders:send {--hour=18 : Local hour (team timezone) at which to send} {--force : Ignore the hour check}';

    protected $description = "Email tomorrow's practices and games to members who turned reminders on";

    public function handle(): int
    {
        $sent = 0;

        foreach (Team::query()->get() as $team) {
            $now = now($team->timezone ?: config('app.timezone'));

            if (! $this->option('force') && $now->hour !== (int) $this->option('hour')) {
                continue;
            }

            $tomorrow = $team->today()->addDay();
            $events = $this->eventsOn($team, $tomorrow);

            // Run hourly without ever mailing a team twice for the same day.
            if ($events === [] || ! Cache::add("reminders:{$team->id}:{$tomorrow->toDateString()}", true, now()->addDays(2))) {
                continue;
            }

            foreach ($team->members()->where('email_reminders', true)->get() as $member) {
                Mail::to($member)->send(new EventReminder($team, $tomorrow, $events));
                $sent++;
            }
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<array{kind: string, title: string, time: string|null, location: string|null}>
     */
    private function eventsOn(Team $team, CarbonInterface $date): array
    {
        $practices = $team->practices()->where('is_template', false)->whereDate('date', $date)->get()
            ->map(fn ($p) => ['kind' => 'Practice', 'title' => $p->name, 'time' => $this->time($p->start_time), 'location' => $p->location]);

        $games = $team->gamePlans()->whereDate('date', $date)->get()
            ->map(fn ($g) => ['kind' => 'Game', 'title' => $g->opponent ? "vs {$g->opponent}" : $g->name, 'time' => $this->time($g->kickoff), 'location' => $g->location]);

        return array_values($practices->concat($games)->all());
    }

    private function time(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
