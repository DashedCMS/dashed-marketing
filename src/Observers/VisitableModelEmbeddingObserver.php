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
        $text = trim(implode(' ', array_filter([
            $model->name ?? $model->title ?? '',
            $model->meta_title ?? $model->seo_title ?? '',
            $model->short_description ?? $model->meta_description ?? '',
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

        RebuildContentEmbeddingJob::dispatch($model::class, $model->getKey())->onQueue('embeddings');
    }

    public function deleted(Model $model): void
    {
        ContentEmbedding::query()
            ->where('embeddable_type', $model::class)
            ->where('embeddable_id', $model->getKey())
            ->delete();
    }
}
