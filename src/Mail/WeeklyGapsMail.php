<?php

namespace Dashed\DashedMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class WeeklyGapsMail extends Mailable implements SendsToTelegram
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

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Lege dagen in social planning',
            fields: [
                'Aantal lege dagen' => (string) $this->emptyDates->count(),
            ],
            emoji: '📅',
        );
    }

    public static function makeForTest(): ?self
    {
        $emptyDates = collect([
            now()->addDays(2)->startOfDay(),
            now()->addDays(5)->startOfDay(),
            now()->addDays(9)->startOfDay(),
        ]);

        return new self(
            emptyDates: $emptyDates,
            siteName: (string) (\Dashed\DashedCore\Models\Customsetting::get('site_name') ?: config('app.name')),
        );
    }
}
