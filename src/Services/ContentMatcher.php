<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentEmbedding;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Database\Eloquent\Model;

class ContentMatcher
{
    public function __construct(
        protected KeywordNormalizer $normalizer,
        protected EmbeddingService $embeddings,
    ) {}

    /** @return array<int, string> */
    public function candidateContentTypes(?string $intent): array
    {
        return match ($intent) {
            'transactional' => ['product', 'category', 'landing_page'],
            'commercial' => ['category', 'landing_page', 'product'],
            'informational' => ['blog', 'faq', 'landing_page'],
            'navigational' => ['landing_page', 'category'],
            default => ['blog', 'landing_page', 'category'],
        };
    }

    public function match(Keyword $keyword): ?array
    {
        $contentTypes = $this->candidateContentTypes($keyword->search_intent);
        $routeModels = $this->routeModelsFor($contentTypes);

        $textHighs = [];
        $candidates = [];

        foreach ($routeModels as $modelClass) {
            foreach ($modelClass::query()->limit(200)->get() as $entity) {
                $score = $this->textScore($keyword->keyword, $entity);

                if ($score >= config('dashed-marketing-content.matcher.text_high_threshold', 0.75)) {
                    $textHighs[] = ['entity' => $entity, 'score' => $score, 'strategy' => 'text'];
                } elseif ($score >= config('dashed-marketing-content.matcher.text_candidate_threshold', 0.40)) {
                    $candidates[] = ['entity' => $entity, 'score' => $score, 'strategy' => 'text'];
                }
            }
        }

        if (! empty($textHighs)) {
            usort($textHighs, fn ($a, $b) => $b['score'] <=> $a['score']);

            return $this->toMatchResult($textHighs[0]);
        }

        $embeddingResults = $this->embeddingPass($keyword, $routeModels);
        foreach ($embeddingResults as $result) {
            if ($result['score'] >= config('dashed-marketing-content.matcher.embedding_high_threshold', 0.90)) {
                return $this->toMatchResult($result);
            }
            if ($result['score'] >= config('dashed-marketing-content.matcher.embedding_candidate_threshold', 0.80)) {
                $candidates[] = $result;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Sanity filter: drop candidates that share zero non-stopword tokens
        // with the keyword in name/slug/meta fields. This prevents the AI
        // from confirming a semantically-adjacent-but-lexically-unrelated
        // record (e.g. "familie beeldjes" matching an unrelated vase because
        // both live in the same 3D-print domain).
        $filtered = array_values(array_filter(
            $candidates,
            fn (array $c) => $this->hasTokenOverlap($keyword, $c['entity']),
        ));

        if (empty($filtered)) {
            return null;
        }

        $top = array_slice($filtered, 0, config('dashed-marketing-content.matcher.top_n_ai_candidates', 5));

        return $this->aiConfirm($keyword, $top);
    }

    /**
     * Does any non-stopword keyword-token appear in the entity's name,
     * slug, meta title or meta description?
     */
    protected function hasTokenOverlap(Keyword $keyword, Model $entity): bool
    {
        $locale = $keyword->locale ?? 'nl';
        $keywordTokens = $this->normalizer->tokens($keyword->keyword, $locale);

        if (empty($keywordTokens)) {
            return true; // keyword itself is all stopwords - don't over-filter
        }

        $haystack = implode(' ', array_filter([
            $this->stringify($entity->name ?? $entity->title ?? '', $locale),
            $this->stringify($entity->slug ?? '', $locale),
            $this->stringify($entity->meta_title ?? $entity->seo_title ?? '', $locale),
            $this->stringify($entity->meta_description ?? $entity->short_description ?? '', $locale),
        ]));

        if ($haystack === '') {
            return false;
        }

        $haystackTokens = $this->normalizer->tokens($haystack, $locale);

        return ! empty(array_intersect($keywordTokens, $haystackTokens));
    }

    private function stringify(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            $value = $value[$locale] ?? reset($value) ?? '';
        }

        return (string) $value;
    }

    /**
     * @param  array<int, string>  $contentTypes
     * @return array<int, class-string<Model>>
     */
    protected function routeModelsFor(array $contentTypes): array
    {
        $map = [
            'product' => 'Dashed\\DashedEcommerceCore\\Models\\Product',
            'category' => 'Dashed\\DashedEcommerceCore\\Models\\ProductCategory',
            'landing_page' => 'Dashed\\DashedCore\\Models\\Page',
            'blog' => 'Dashed\\DashedArticles\\Models\\Article',
        ];

        $models = [];
        foreach ($contentTypes as $type) {
            if (isset($map[$type]) && class_exists($map[$type])) {
                $models[] = $map[$type];
            }
        }

        return $models;
    }

    protected function textScore(string $keyword, Model $entity): float
    {
        $kwTokens = $this->normalizer->tokens($keyword);
        $titleField = $entity->name ?? $entity->title ?? '';
        $slugField = $entity->slug ?? '';
        $metaTitle = $entity->meta_title ?? $entity->seo_title ?? '';
        $metaDescription = $entity->meta_description ?? $entity->short_description ?? '';

        $slugHit = $this->normalizer->substringContains($slugField, $keyword) ? 3.0 : 0.0;
        $metaTitleHit = $this->normalizer->substringContains($metaTitle, $keyword) ? 2.0 : 0.0;

        $titleJaccard = $this->normalizer->jaccard($kwTokens, $this->normalizer->tokens($titleField)) * 2.0;
        $metaDescJaccard = $this->normalizer->jaccard($kwTokens, $this->normalizer->tokens($metaDescription)) * 1.0;

        $raw = $slugHit + $metaTitleHit + $titleJaccard + $metaDescJaccard;
        $max = 8.0;

        return min(1.0, $raw / $max);
    }

    /**
     * @param  array<int, class-string<Model>>  $routeModels
     * @return array<int, array{entity: Model, score: float, strategy: string}>
     */
    protected function embeddingPass(Keyword $keyword, array $routeModels): array
    {
        $vector = $this->embeddings->embedText($keyword->keyword);
        if ($vector === null) {
            return [];
        }

        $results = [];
        foreach ($routeModels as $modelClass) {
            $embeddings = ContentEmbedding::query()
                ->where('embeddable_type', $modelClass)
                ->get();

            foreach ($embeddings as $embedding) {
                $similarity = $this->embeddings->cosineSimilarity($vector, $embedding->vector);
                if ($similarity <= 0) {
                    continue;
                }
                $entity = $modelClass::find($embedding->embeddable_id);
                if ($entity === null) {
                    continue;
                }
                $results[] = ['entity' => $entity, 'score' => $similarity, 'strategy' => 'embedding'];
            }
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    /** @param array<int, array{entity: Model, score: float, strategy: string}> $candidates */
    protected function aiConfirm(Keyword $keyword, array $candidates): ?array
    {
        $options = [];
        foreach ($candidates as $i => $candidate) {
            $entity = $candidate['entity'];
            $options[] = [
                'index' => $i,
                'type' => $entity::class,
                'id' => $entity->getKey(),
                'title' => $entity->name ?? $entity->title ?? '(untitled)',
                'slug' => $entity->slug ?? '',
                'snippet' => mb_substr((string) ($entity->meta_description ?? $entity->short_description ?? ''), 0, 200),
            ];
        }

        $prompt = "Keyword: {$keyword->keyword}\n\nCandidates:\n".json_encode($options, JSON_PRETTY_PRINT).
            "\n\nWhich candidate (if any) describes the same search intent as the keyword? ".
            'Return JSON {"match_index": int|null, "confidence": float 0-1, "reason": string}.';

        $response = Ai::json($prompt) ?? [];
        $matchIndex = $response['match_index'] ?? null;
        $confidence = (float) ($response['confidence'] ?? 0);

        if ($matchIndex === null) {
            return null;
        }
        if ($confidence < config('dashed-marketing-content.matcher.ai_confirm_threshold', 0.70)) {
            return null;
        }

        $candidate = $candidates[$matchIndex] ?? null;
        if ($candidate === null) {
            return null;
        }

        $candidate['strategy'] = 'ai';
        $candidate['score'] = $confidence;

        return $this->toMatchResult($candidate);
    }

    /** @param array{entity: Model, score: float, strategy: string} $result */
    protected function toMatchResult(array $result): array
    {
        return [
            'subject_type' => $result['entity']::class,
            'subject_id' => $result['entity']->getKey(),
            'score' => $result['score'],
            'strategy' => $result['strategy'],
        ];
    }
}
