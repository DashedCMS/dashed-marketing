<?php

namespace Dashed\DashedMarketing\DTOs;

enum PostStatus: string
{
    case PENDING_MANUAL = 'pending_manual';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case UNKNOWN = 'unknown';
}
