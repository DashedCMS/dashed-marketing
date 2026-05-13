<?php

namespace Dashed\DashedMarketing\Mail;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedMarketing\Models\SocialPost;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PostMissedMail extends Mailable implements SendsToTelegram
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SocialPost $post,
        public string $siteName,
    ) {}

    public function envelope(): Envelope
    {
        $title = str($this->post->caption)->limit(40);

        return new Envelope(
            subject: "[{$this->siteName}] Post '{$title}' van gisteren is nog niet gepost",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'dashed-marketing::mail.post-missed',
        );
    }

    public function telegramSummary(): TelegramSummary
    {
        return new TelegramSummary(
            title: 'Post gemist',
            fields: [
                'Post' => str($this->post->caption ?? '')->limit(60)->toString() ?: '-',
                'Gepland op' => $this->post->scheduled_at?->format('d-m-Y H:i') ?? '-',
            ],
            emoji: '😴',
        );
    }

    public static function makeForTest(): ?self
    {
        $post = SocialPost::withoutGlobalScopes()->latest()->first()
            ?? new SocialPost([
                'caption' => 'Test post die gemist is',
                'scheduled_at' => now()->subHours(2),
            ]);

        return new self(
            post: $post,
            siteName: (string) (Customsetting::get('site_name') ?: config('app.name')),
        );
    }
}
