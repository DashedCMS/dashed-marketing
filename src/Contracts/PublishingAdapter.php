<?php

namespace Dashed\DashedMarketing\Contracts;

use Dashed\DashedMarketing\DTOs\PostStatus;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\DTOs\PublishResult;

interface PublishingAdapter
{
    public function publish(SocialPost $post): PublishResult;

    public function getStatus(SocialPost $post): PostStatus;

    public function supports(string $platform): bool;
}
