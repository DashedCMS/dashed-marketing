<?php

namespace Dashed\DashedMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedMarketing\Models\SocialPost;

class PostMissedMail extends Mailable
{
    use Queueable, SerializesModels;

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
}
