<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedMarketing\Models\SocialNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSocialNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Mailable $mailable,
        public string $recipient,
        public string $type,
        public string $siteId,
    ) {}

    public function handle(): void
    {
        AdminNotifier::send($this->mailable, $this->recipient);

        SocialNotificationLog::create([
            'type' => $this->type,
            'site_id' => $this->siteId,
            'sent_at' => now(),
            'recipient' => $this->recipient,
            'content' => $this->type,
        ]);
    }
}
