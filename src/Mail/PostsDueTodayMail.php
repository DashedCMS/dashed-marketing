<?php

namespace Dashed\DashedMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class PostsDueTodayMail extends Mailable implements SendsToTelegram
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Collection $posts,
        public string $siteName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->siteName}] Je hebt vandaag {$this->posts->count()} posts te plaatsen",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'dashed-marketing::mail.posts-due-today',
        );
    }

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Posts voor vandaag',
            fields: [
                'Aantal' => (string) $this->posts->count(),
            ],
            emoji: '📅',
        );
    }

    public static function makeForTest(): ?self
    {
        $posts = SocialPost::withoutGlobalScopes()
            ->whereDate('scheduled_at', today())
            ->limit(3)
            ->get();

        if ($posts->isEmpty()) {
            $posts = collect([
                new SocialPost([
                    'caption' => 'Test post 1 voor vandaag',
                    'scheduled_at' => now()->setTime(10, 0),
                ]),
                new SocialPost([
                    'caption' => 'Test post 2 voor vandaag',
                    'scheduled_at' => now()->setTime(15, 0),
                ]),
            ]);
        }

        return new self(
            posts: $posts,
            siteName: (string) (\Dashed\DashedCore\Models\Customsetting::get('site_name') ?: config('app.name')),
        );
    }
}
