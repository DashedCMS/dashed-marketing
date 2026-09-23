<?php

namespace Dashed\DashedMarketing\Commands;

use Illuminate\Console\Command;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Jobs\PublishSocialPostJob;

class PublishDueSocialPostsCommand extends Command
{
    protected $signature = 'social:publish-due';

    protected $description = 'Dispatch PublishSocialPostJob for scheduled posts whose scheduled_at is due.';

    public function handle(): void
    {
        $posts = SocialPost::withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($posts->isEmpty()) {
            $this->info('No due social posts to publish.');

            return;
        }

        foreach ($posts as $post) {
            // Claim de post voordat we hem insturen. Dit commando draait elke
            // minuut, terwijl PublishSocialPostJob de status pas op
            // 'publishing' zet zodra de job daadwerkelijk start. Met een volle
            // wachtrij stond dezelfde post dus minutenlang nog op 'scheduled'
            // en werd hij elke minuut opnieuw ingestuurd: op 22 september 2026
            // plaatste Lovora zo 15 keer dezelfde story.
            $geclaimd = SocialPost::withoutGlobalScopes()
                ->whereKey($post->getKey())
                ->where('status', 'scheduled')
                ->update(['status' => 'publishing']);

            if ($geclaimd === 0) {
                continue;
            }

            $post->status = 'publishing';

            PublishSocialPostJob::dispatch($post);
            $this->info("Dispatched publish job for post #{$post->id} (site {$post->site_id}).");
        }
    }
}
