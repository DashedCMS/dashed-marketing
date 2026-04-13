<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Services\EmbeddingService;

class RebuildContentEmbeddingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $modelClass,
        public int|string $modelId,
    ) {
    }

    public function handle(EmbeddingService $embeddings): void
    {
        if (! class_exists($this->modelClass)) {
            return;
        }

        $model = $this->modelClass::find($this->modelId);
        if ($model === null) {
            return;
        }

        $text = trim(implode(' ', array_filter([
            $model->name ?? $model->title ?? '',
            $model->meta_title ?? $model->seo_title ?? '',
            $model->short_description ?? $model->meta_description ?? '',
        ])));

        if ($text === '') {
            return;
        }

        $embeddings->rebuild($model, $text);
    }
}
