<?php

namespace Dashed\DashedMarketing\Contracts;

use Dashed\DashedMarketing\DTOs\KeywordResearchResult;
use Illuminate\Support\Collection;

interface KeywordResearchAdapter
{
    public function research(string $seedKeyword, string $locale): KeywordResearchResult;

    public function getSuggestions(string $keyword): Collection;

    public function getVolume(array $keywords): array;

    public function getPositions(string $domain, array $keywords): ?array;
}
