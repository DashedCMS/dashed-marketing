<?php

namespace Dashed\DashedMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedMarketing\Models\SocialHoliday;

class HolidayReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SocialHoliday $holiday,
        public string $siteName,
    ) {
    }

    public function envelope(): Envelope
    {
        $daysUntil = now()->startOfDay()->diffInDays($this->holiday->date->startOfDay());
        $when = $daysUntil === 0 ? 'vandaag' : "over {$daysUntil} " . ($daysUntil === 1 ? 'dag' : 'dagen');

        return new Envelope(
            subject: "[{$this->siteName}] Herinnering: {$this->holiday->name} is {$when}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'dashed-marketing::mail.holiday-reminder',
        );
    }
}
