<?php

namespace Dashed\DashedMarketing\DTOs;

class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalUrl = null,
        public readonly ?string $externalId = null,
        public readonly ?string $error = null,
    ) {}
}
