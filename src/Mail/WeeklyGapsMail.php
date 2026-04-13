<?php

namespace Dashed\DashedMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;

class WeeklyGapsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Collection $emptyDates,
        public string $siteName,
    ) {
    }

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
