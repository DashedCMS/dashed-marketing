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

    /**
     * Nooit automatisch opnieuw: een afgekapte poging kan bij de adapter gewoon
     * gelukt zijn, en een tweede poging is dan een dubbele post.
     */
    public int $tries = 1;

    /** Ruimer dan de wachttijd van de adapter; video's duren lang. */
    public int $timeout = 180;

    public function __construct(
        public SocialPost $post,
        public bool $force = false,
    ) {
    }

    public function handle(): void
    {
        // Een post met een external_id ligt al bij de publicatie-adapter.
        // Nog een keer publiceren maakt daar een tweede post van, en dus een
        // duplicaat op het kanaal. Dat gebeurde bij Lovora: dezelfde story
        // stond vijftien keer op Facebook en Instagram. Alleen een bewuste
        // actie (force) mag er nog een keer overheen.
        if ($this->post->external_id && ! $this->force) {
            Log::info("Social post #{$this->post->id} is al gepubliceerd bij de adapter, publicatie overgeslagen.");

            return;
        }

        $adapter = app(PublishingAdapter::class, ['site_id' => $this->post->site_id]);

        $this->post->update(['status' => 'publishing']);

        $result = $adapter->publish($this->post);

        $attempt = $this->post->retry_count + 1;

        if ($result->success) {
            $now = now();
            $update = [
                'status' => 'posted',
                'posted_at' => $now,
                'retry_count' => $attempt,
            ];

            $channels = is_array($this->post->channels) ? $this->post->channels : [];
            $perChannel = is_array($this->post->posted_at_per_channel) ? $this->post->posted_at_per_channel : [];
            foreach ($channels as $slug) {
                $perChannel[$slug] = $now->toIso8601String();
            }
            $update['posted_at_per_channel'] = $perChannel;

            if ($result->externalId) {
                $update['external_id'] = $result->externalId;
                // External adapter: actual state is confirmed by the sync-post-statuses polling command
                $update['status'] = $this->post->scheduled_at?->isFuture() ? 'scheduled' : 'publishing';
                unset($update['posted_at']);
                // Per-channel posted_at is also confirmed by polling, so clear pre-fill in that case
                unset($update['posted_at_per_channel']);
            }

            if ($result->externalUrl) {
                $update['post_url'] = $result->externalUrl;
            }

            $this->post->update($update);
        } else {
            $this->post->update([
                'status' => 'publish_failed',
                'retry_count' => $attempt,
                'external_data' => array_merge(
                    $this->post->external_data ?? [],
                    ['last_error' => $result->error, 'last_error_at' => now()->toIso8601String()]
                ),
            ]);

            Log::warning("Social post publish failed for post #{$this->post->id}: {$result->error}");
        }
    }

    /**
     * Na een afgekapte job (time-out) bleef de post anders voor altijd op
     * 'publishing' staan, zonder dat iemand het zag.
     */
    public function failed(\Throwable $exception): void
    {
        $post = $this->post->fresh();

        if (! $post || $post->external_id) {
            return;
        }

        $post->update([
            'status' => 'publish_failed',
            'external_data' => array_merge($post->external_data ?? [], [
                'last_error' => 'Publiceren afgebroken: de post kan toch geplaatst zijn, controleer dat voordat je hem opnieuw probeert. ('.$exception->getMessage().')',
                'last_error_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::warning("Social post publish job failed for post #{$post->id}: {$exception->getMessage()}");
    }
}
