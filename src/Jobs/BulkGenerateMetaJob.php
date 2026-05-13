<?php

namespace Dashed\DashedMarketing\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;

/**
 * Fans GenerateMetaForRecordJob out over every record of the chosen
 * visitable models. Used by the AI-settings "Genereer meta voor alle
 * modellen" header action. Records are paginated to keep memory low and
 * each per-record job runs separately so a single failure does not abort
 * the rest of the run.
 */
final class BulkGenerateMetaJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesQueueFailures;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $backoff = 0;

    /**
     * @param  array<int, string>  $models  camelCased route-model keys (see cms()->builder('routeModels')).
     */
    public function __construct(
        public array $models,
        public ?string $instruction = null,
        public bool $overwrite = false,
    ) {
    }

    public function handle(): void
    {
        $registry = [];
        try {
            $registry = (array) cms()->builder('routeModels');
        } catch (Throwable $e) {
            Log::warning('BulkGenerateMetaJob: could not load routeModels', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $localeCount = count(Locales::getLocalesArray());

        $totalRecords = 0;
        $totalSkipped = 0;
        $totalDispatched = 0;
        $totalLocalesTouched = 0;

        foreach ($this->models as $modelKey) {
            if (! isset($registry[$modelKey])) {
                continue;
            }

            $class = $registry[$modelKey]['class'] ?? null;
            if (! is_string($class) || ! class_exists($class)) {
                $totalSkipped++;

                continue;
            }

            if (! $this->modelSupportsMeta($class)) {
                Log::info('BulkGenerateMetaJob: skipping model without translatable metadata', [
                    'model' => $class,
                ]);

                continue;
            }

            try {
                $class::query()
                    ->select(['id'])
                    ->chunkById(50, function ($records) use ($class, &$totalRecords, &$totalDispatched, &$totalLocalesTouched, $localeCount): void {
                        foreach ($records as $record) {
                            $totalRecords++;

                            try {
                                GenerateMetaForRecordJob::dispatch(
                                    $class,
                                    $record->getKey(),
                                    $this->instruction,
                                    $this->overwrite,
                                );
                                $totalDispatched++;
                                $totalLocalesTouched += $localeCount;
                            } catch (Throwable $e) {
                                Log::warning('BulkGenerateMetaJob: dispatch failed', [
                                    'model' => $class,
                                    'id' => $record->getKey(),
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    });
            } catch (Throwable $e) {
                Log::warning('BulkGenerateMetaJob: chunk iteration failed', [
                    'model' => $class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('BulkGenerateMetaJob: summary', [
            'models' => $this->models,
            'overwrite' => $this->overwrite,
            'total_records' => $totalRecords,
            'total_dispatched' => $totalDispatched,
            'total_skipped' => $totalSkipped,
            'total_locales_touched' => $totalLocalesTouched,
        ]);
    }

    protected function modelSupportsMeta(string $class): bool
    {
        try {
            $instance = new $class;
        } catch (Throwable) {
            return false;
        }

        if (! method_exists($instance, 'metadata')) {
            return false;
        }

        try {
            $metadata = $instance->metadata()->getRelated();
        } catch (Throwable) {
            return false;
        }

        if (! is_object($metadata)) {
            return false;
        }

        $traits = class_uses_recursive($metadata::class);

        return isset($traits[HasTranslations::class]);
    }

    public function extraLogContext(): array
    {
        return [
            'models' => $this->models,
            'overwrite' => $this->overwrite,
        ];
    }
}
