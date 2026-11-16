<?php

namespace Dashed\DashedMarketing\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Support\MarketingTask;
use Dashed\DashedMarketing\Services\MetaGenerator;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;

/**
 * Generates meta titles and descriptions for a single CMS record across
 * every configured locale. Triggered from the per-record header action and
 * also fanned out by BulkGenerateMetaJob.
 */
final class GenerateMetaForRecordJob implements ShouldQueue
{
    use Dispatchable;
    use HandlesQueueFailures;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        public string $subjectType,
        public int|string $subjectId,
        public ?string $instruction = null,
        public bool $overwrite = false,
        public ?string $taskKey = null,
        public ?int $userId = null,
    ) {
    }

    public const TASK_TYPE = 'meta-generate';

    public function handle(): void
    {
        // In het takenicoon van dashed-core: een onderdeel van de bulkronde
        // ($taskKey), anders een eigen taak van één stap.
        $key = MarketingTask::keyFor($this, $this->taskKey);
        MarketingTask::open(
            self::TASK_TYPE,
            __('Meta genereren: :naam', ['naam' => class_basename($this->subjectType).' #'.$this->subjectId]),
            $key,
            1,
            $this->userId,
            notify: true,
        );

        $this->generate();

        MarketingTask::advance(self::TASK_TYPE, $key, __('De meta title en description zijn gegenereerd.'));
    }

    public function failed(Throwable $e): void
    {
        MarketingTask::advance(self::TASK_TYPE, MarketingTask::keyFor($this, $this->taskKey), error: $e);

        $this->reportFailure($e);
    }

    private function generate(): void
    {
        if (! class_exists($this->subjectType)) {
            Log::warning('GenerateMetaForRecordJob: subject class not found', [
                'subject_type' => $this->subjectType,
                'subject_id' => $this->subjectId,
            ]);

            return;
        }

        try {
            $subject = ($this->subjectType)::find($this->subjectId);
        } catch (Throwable $e) {
            Log::warning('GenerateMetaForRecordJob: subject lookup failed', [
                'subject_type' => $this->subjectType,
                'subject_id' => $this->subjectId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! $subject) {
            return;
        }

        $locales = array_keys(Locales::getLocalesArray());
        if ($locales === []) {
            return;
        }

        $results = app(MetaGenerator::class)
            ->generateForRecord($subject, $locales, $this->instruction, $this->overwrite);

        Log::info('GenerateMetaForRecordJob: done', [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'overwrite' => $this->overwrite,
            'results' => $results,
        ]);
    }

    public function extraLogContext(): array
    {
        return [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'overwrite' => $this->overwrite,
        ];
    }
}
