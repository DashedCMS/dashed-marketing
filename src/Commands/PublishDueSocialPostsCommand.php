<?php

namespace Dashed\DashedMarketing\Commands;

use Dashed\DashedMarketing\Jobs\PublishSocialPostJob;
use Dashed\DashedMarketing\Models\SocialPost;
use Illuminate\Console\Command;

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
            PublishSocialPostJob::dispatch($post);
            $this->info("Dispatched publish job for post #{$post->id} (site {$post->site_id}).");
        }
    }
}
