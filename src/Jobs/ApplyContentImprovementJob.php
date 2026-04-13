<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ApplyContentImprovementJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $improvementId,
        public string $proposalKey,
        public mixed $editedValue = null,
        public ?int $userId = null,
    ) {}

    public function handle(): void
    {
        $improvement = SeoImprovement::findOrFail($this->improvementId);
        $subject = $improvement->subject;
        if ($subject === null) {
            return;
        }

        $newValue = $this->editedValue
            ?? ($improvement->block_proposals[$this->proposalKey] ?? $improvement->field_proposals[$this->proposalKey] ?? null);

        if ($newValue === null) {
            return;
        }

        $previousValue = $this->readPrevious($subject, $this->proposalKey);

        DB::transaction(function () use ($subject, $improvement, $newValue, $previousValue) {
            $this->writeValue($subject, $this->proposalKey, $newValue);

            ContentApplyLog::create([
                'seo_improvement_id' => $improvement->id,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'field_key' => $this->proposalKey,
                'previous_value' => json_encode($previousValue),
                'new_value' => json_encode($newValue),
                'applied_by' => $this->userId,
                'applied_at' => now(),
            ]);

            $improvement->markProposal($this->proposalKey, 'applied');
        });
    }

    protected function readPrevious($subject, string $key): mixed
    {
        if (str_starts_with($key, 'block.')) {
            $blocks = $subject->customBlocks?->blocks ?? [];

            return $blocks[substr($key, 6)] ?? null;
        }

        return $subject->{$key} ?? null;
    }

    protected function writeValue($subject, string $key, mixed $value): void
    {
        if (str_starts_with($key, 'block.')) {
            $blockKey = substr($key, 6);
            $blocks = $subject->customBlocks?->blocks ?? [];
            $blocks[$blockKey] = $value;

            if (method_exists($subject, 'customBlocks')) {
                $subject->customBlocks()->updateOrCreate(
                    ['blockable_type' => $subject::class, 'blockable_id' => $subject->getKey()],
                    ['blocks' => $blocks],
                );
            }

            return;
        }

        $subject->update([$key => $value]);
    }
}
