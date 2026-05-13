<?php

namespace Dashed\DashedMarketing\Observers;

use Dashed\DashedMarketing\Jobs\RebuildContentEmbeddingJob;
use Dashed\DashedMarketing\Models\ContentEmbedding;
use Dashed\DashedMarketing\Services\EmbeddingService;
use Illuminate\Database\Eloquent\Model;

class VisitableModelEmbeddingObserver
{
    public function __construct(protected EmbeddingService $embeddings) {}

    public function saved(Model $model): void
    {
        $flatten = function ($value): string {
            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(
                    fn ($v) => is_scalar($v) ? (string) $v : '',
                    $value,
                )));
            }

            return is_scalar($value) ? (string) $value : '';
        };

        $text = trim(implode(' ', array_filter([
            $flatten($model->name ?? $model->title ?? ''),
            $flatten($model->meta_title ?? $model->seo_title ?? ''),
            $flatten($model->short_description ?? $model->meta_description ?? ''),
        ])));

        if ($text === '') {
            return;
        }

        $hash = $this->embeddings->contentHash($text);

        $existing = ContentEmbedding::query()
            ->where('embeddable_type', $model::class)
            ->where('embeddable_id', $model->getKey())
            ->first();

        if ($existing !== null && $existing->content_hash === $hash) {
            return;
        }

        RebuildContentEmbeddingJob::dispatch($model::class, $model->getKey());
    }

    public function deleted(Model $model): void
    {
        ContentEmbedding::query()
            ->where('embeddable_type', $model::class)
            ->where('embeddable_id', $model->getKey())
            ->delete();
    }
}
