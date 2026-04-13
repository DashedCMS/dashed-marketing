<?php

namespace Dashed\DashedMarketing\Contracts;

interface KeywordDataProvider
{
    public function name(): string;

    /**
     * @param  array<int, string>  $keywords
     * @return array<string, array{volume_exact: ?int, cpc: ?float, search_intent: ?string, difficulty: ?string}>
     */
    public function enrich(array $keywords, string $locale): array;

    public function supports(string $capability): bool;
}
