<?php

namespace Dashed\DashedMarketing\Adapters;

use Dashed\DashedAi\Facades\Ai;
use Illuminate\Support\Collection;
use Dashed\DashedMarketing\DTOs\KeywordResearchResult;
use Dashed\DashedMarketing\Contracts\KeywordResearchAdapter;

class ClaudeKeywordAdapter implements KeywordResearchAdapter
{
    public function research(string $seedKeyword, string $locale): KeywordResearchResult
    {
        $prompt = $this->buildResearchPrompt($seedKeyword, $locale);
        $result = Ai::json($prompt);

        if (! $result || ! isset($result['keywords'])) {
            return new KeywordResearchResult(keywords: [], clusters: [], error: 'Claude gaf geen bruikbaar antwoord.');
        }

        return new KeywordResearchResult(keywords: $result['keywords'] ?? [], clusters: $result['clusters'] ?? []);
    }

    public function getSuggestions(string $keyword): Collection
    {
        $prompt = <<<PROMPT
        Geef 10 gerelateerde zoekwoorden voor "{$keyword}" in het Nederlands.
        Varieer tussen long-tail, LSI/semantisch, en vraag-type keywords.

        Retourneer UITSLUITEND geldig JSON:
        {"suggestions": ["keyword1", "keyword2", ...]}
        PROMPT;

        $result = Ai::json($prompt);

        return collect($result['suggestions'] ?? []);
    }

    public function getVolume(array $keywords): array
    {
        $keywordList = implode(', ', array_map(fn ($k) => "\"{$k}\"", $keywords));

        $prompt = <<<PROMPT
        Geef een inschatting van het maandelijks zoekvolume voor de volgende Nederlandse zoekwoorden: {$keywordList}

        Gebruik categorieën: low (0-100), medium (100-1000), high (1000+).
        Retourneer UITSLUITEND geldig JSON:
        {"volumes": {"keyword": "low|medium|high", ...}}
        PROMPT;

        $result = Ai::json($prompt);

        return $result['volumes'] ?? [];
    }

    public function getPositions(string $domain, array $keywords): ?array
    {
        return null;
    }

    private function buildResearchPrompt(string $seedKeyword, string $locale): string
    {
        return <<<PROMPT
        Voer een zoekwoord onderzoek uit voor het seed keyword "{$seedKeyword}" (taal: {$locale}).

        Genereer:
        1. 15-25 gerelateerde keywords met type (primary/secondary/long_tail/lsi/question),
           search intent (informational/navigational/commercial/transactional),
           moeilijkheid (easy/medium/hard), en volume indicatie (low/medium/high).
        2. 3-5 content clusters die deze keywords logisch groeperen.

        Retourneer UITSLUITEND geldig JSON:
        {
            "keywords": [
                {"keyword": "...", "type": "...", "search_intent": "...", "difficulty": "...", "volume_indication": "..."}
            ],
            "clusters": [
                {"name": "...", "theme": "...", "content_type": "blog|landing_page|category|faq|product", "description": "...", "keyword_indices": [0, 1, 2]}
            ]
        }
        PROMPT;
    }
}
