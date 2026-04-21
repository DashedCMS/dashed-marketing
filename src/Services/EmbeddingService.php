<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedAi\Exceptions\EmbeddingNotSupportedException;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentEmbedding;
use Illuminate\Database\Eloquent\Model;

class EmbeddingService
{
    public function embeddingFor(Model $model): ?array
    {
        $record = ContentEmbedding::query()
            ->where('embeddable_type', $model::class)
            ->where('embeddable_id', $model->getKey())
            ->first();

        return $record?->vector;
    }

    public function rebuild(Model $model, string $text): ?ContentEmbedding
    {
        if (! config('dashed-marketing-content.embeddings.enabled', true)) {
            return null;
        }

        try {
            $vector = Ai::embed($text, [
                'model' => config('dashed-marketing-content.embeddings.model'),
                'provider' => config('dashed-marketing-content.embeddings.provider'),
            ]);
        } catch (EmbeddingNotSupportedException) {
            return null;
        }

        return ContentEmbedding::updateOrCreate(
            [
                'embeddable_type' => $model::class,
                'embeddable_id' => $model->getKey(),
            ],
            [
                'vector' => $vector,
                'content_hash' => hash('sha256', $text),
            ],
        );
    }

    public function contentHash(string $text): string
    {
        return hash('sha256', $text);
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $magA += $value * $value;
            $magB += $b[$i] * $b[$i];
        }

        if ($magA === 0.0 || $magB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }

    public function embedText(string $text): ?array
    {
        if (! config('dashed-marketing-content.embeddings.enabled', true)) {
            return null;
        }

        try {
            return Ai::embed($text, [
                'model' => config('dashed-marketing-content.embeddings.model'),
                'provider' => config('dashed-marketing-content.embeddings.provider'),
            ]);
        } catch (EmbeddingNotSupportedException) {
            return null;
        }
    }
}
