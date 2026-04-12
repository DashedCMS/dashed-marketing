<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedMarketing\Models\SocialNotificationLog;

class SendSocialNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Mailable $mailable,
        public string $recipient,
        public string $type,
        public string $siteId,
    ) {}

    public function handle(): void
    {
        Mail::to($this->recipient)->send($this->mailable);

        SocialNotificationLog::create([
            'type' => $this->type,
            'site_id' => $this->siteId,
            'sent_at' => now(),
            'recipient' => $this->recipient,
            'content' => $this->type,
        ]);
    }
}
