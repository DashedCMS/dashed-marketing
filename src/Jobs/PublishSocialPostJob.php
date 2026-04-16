<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Contracts\PublishingAdapter;

class PublishSocialPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SocialPost $post,
    ) {
    }

    public function handle(): void
    {
        $adapter = app(PublishingAdapter::class, ['site_id' => $this->post->site_id]);

        $this->post->update(['status' => 'publishing']);

        $result = $adapter->publish($this->post);

        if ($result->success) {
            $update = ['status' => 'posted', 'posted_at' => now()];

            if ($result->externalId) {
                $update['external_id'] = $result->externalId;
                // External adapter: actual confirmation comes via webhook
                $update['status'] = $this->post->scheduled_at?->isFuture() ? 'scheduled' : 'publishing';
                unset($update['posted_at']);
            }

            if ($result->externalUrl) {
                $update['post_url'] = $result->externalUrl;
            }

            $this->post->update($update);
        } else {
            $this->post->update([
                'status' => 'publish_failed',
                'retry_count' => $this->post->retry_count + 1,
                'external_data' => array_merge(
                    $this->post->external_data ?? [],
                    ['last_error' => $result->error, 'last_error_at' => now()->toIso8601String()]
                ),
            ]);

            Log::warning("Social post publish failed for post #{$this->post->id}: {$result->error}");
        }
    }
}
