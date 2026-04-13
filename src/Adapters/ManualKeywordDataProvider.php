<?php

namespace Dashed\DashedMarketing\Adapters;

use Dashed\DashedMarketing\Contracts\KeywordDataProvider;

class ManualKeywordDataProvider implements KeywordDataProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function enrich(array $keywords, string $locale): array
    {
        return [];
    }

    public function supports(string $capability): bool
    {
        return false;
    }
}
