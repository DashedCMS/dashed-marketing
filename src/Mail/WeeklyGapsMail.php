<?php

namespace Dashed\DashedMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WeeklyGapsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Collection $emptyDates,
        public string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->siteName}] Er zijn {$this->emptyDates->count()} dagen zonder geplande posts de komende 2 weken",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'dashed-marketing::mail.weekly-gaps',
        );
    }
}
