<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

class EventReminder extends Mailable
{
    /**
     * @param  list<array{kind: string, title: string, time: string|null, location: string|null}>  $events
     */
    public function __construct(public Team $team, public Carbon $date, public array $events) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tomorrow: {$this->team->name} ({$this->date->format('D, M j')})");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.event-reminder');
    }
}
