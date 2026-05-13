<?php

namespace Dashed\DashedMarketing\DTOs;

class KeywordResearchResult
{
    public function __construct(
        public readonly array $keywords,
        public readonly array $clusters,
        public readonly ?string $error = null,
    ) {}

    public function failed(): bool
    {
        return $this->error !== null;
    }
}
