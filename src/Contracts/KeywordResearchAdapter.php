<?php

namespace Dashed\DashedMarketing\Contracts;

use Illuminate\Support\Collection;
use Dashed\DashedMarketing\DTOs\KeywordResearchResult;

interface KeywordResearchAdapter
{
    public function research(string $seedKeyword, string $locale): KeywordResearchResult;

    public function getSuggestions(string $keyword): Collection;

    public function getVolume(array $keywords): array;

    public function getPositions(string $domain, array $keywords): ?array;
}
