<?php

namespace Dashed\DashedMarketing\Mail;

use Dashed\DashedMarketing\Models\SocialHoliday;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;

class HolidayReminderMail extends Mailable implements SendsToTelegram
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SocialHoliday $holiday,
        public string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        $daysUntil = now()->startOfDay()->diffInDays($this->holiday->date->startOfDay());
        $when = $daysUntil === 0 ? 'vandaag' : "over {$daysUntil} ".($daysUntil === 1 ? 'dag' : 'dagen');

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

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Feestdag herinnering',
            fields: [
                'Feestdag' => $this->holiday->name ?? '-',
                'Datum' => $this->holiday->date?->format('d-m-Y') ?? '-',
            ],
            emoji: '🎉',
        );
    }

    public static function makeForTest(): ?self
    {
        $holiday = SocialHoliday::query()->orderBy('date')->first()
            ?? new SocialHoliday([
                'name' => 'Test feestdag',
                'date' => now()->addDays(7),
            ]);

        return new self(
            holiday: $holiday,
            siteName: (string) (\Dashed\DashedCore\Models\Customsetting::get('site_name') ?: config('app.name')),
        );
    }
}
