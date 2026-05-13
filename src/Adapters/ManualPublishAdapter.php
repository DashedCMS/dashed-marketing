<?php

namespace Dashed\DashedMarketing\Adapters;

use Dashed\DashedMarketing\Contracts\PublishingAdapter;
use Dashed\DashedMarketing\DTOs\PostStatus;
use Dashed\DashedMarketing\DTOs\PublishResult;
use Dashed\DashedMarketing\Models\SocialPost;

class ManualPublishAdapter implements PublishingAdapter
{
    public function publish(SocialPost $post): PublishResult
    {
        return new PublishResult(success: true);
    }

    public function getStatus(SocialPost $post): PostStatus
    {
        if ($post->status === 'posted') {
            return PostStatus::PUBLISHED;
        }

        return PostStatus::PENDING_MANUAL;
    }

    public function supports(string $platform): bool
    {
        return true;
    }
}
