<?php

namespace Dashed\DashedMarketing\Adapters;

use Dashed\DashedMarketing\DTOs\PostStatus;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\DTOs\PublishResult;
use Dashed\DashedMarketing\Contracts\PublishingAdapter;

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
