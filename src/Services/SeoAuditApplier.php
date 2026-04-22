<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Models\SeoAuditBlockSuggestion;
use Dashed\DashedMarketing\Models\SeoAuditFaqSuggestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SeoAuditApplier
{
    /**
     * @param  array<string, mixed>  $selectedIds  keys: meta, blocks, faqs, structured_data, faq_target
     */
    public function applySelected(SeoAudit $audit, array $selectedIds, ?int $userId = null): SeoAuditApplyResult
    {
        $this->ensureApplyable($audit);
        $subject = $audit->subject;
        if (! $subject) {
            $audit->update(['status' => 'failed', 'error_message' => 'Subject verdwenen']);
            throw new RuntimeException('Audit subject is no longer available');
        }

        $result = new SeoAuditApplyResult();

        foreach ((array) ($selectedIds['meta'] ?? []) as $id) {
            $this->applyMeta($audit, $subject, (int) $id, $userId, $result);
        }

        foreach ((array) ($selectedIds['blocks'] ?? []) as $id) {
            $this->applyBlock($audit, $subject, (int) $id, $userId, $result);
        }

        $faqIds = array_map('intval', (array) ($selectedIds['faqs'] ?? []));
        $faqTarget = (string) ($selectedIds['faq_target'] ?? 'existing');
        if (! empty($faqIds)) {
            $this->applyFaqs($audit, $subject, $faqIds, $faqTarget, $userId, $result);
        }

        // Structured data applier lands in Task 4.5.

        $this->updateAuditStatus($audit, $userId, $result);

        return $result;
    }

    public function applyAll(SeoAudit $audit, ?int $userId = null): SeoAuditApplyResult
    {
        return $this->applySelected($audit, [
            'meta' => $audit->metaSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'blocks' => $audit->blockSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'faqs' => $audit->faqSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
            'structured_data' => $audit->structuredDataSuggestions()->whereIn('status', ['pending', 'edited'])->pluck('id')->all(),
        ], $userId);
    }

    public function rollbackAudit(SeoAudit $audit): void
    {
        // Implemented in Task 4.6.
    }

    public function revertOne(ContentApplyLog $log): void
    {
        // Implemented in Task 4.6.
    }

    protected function ensureApplyable(SeoAudit $audit): void
    {
        if (! in_array($audit->status, ['ready', 'partially_applied'], true)) {
            throw new RuntimeException("Audit is niet applyable in status: {$audit->status}");
        }
    }

    protected function applyMeta(SeoAudit $audit, Model $subject, int $id, ?int $userId, SeoAuditApplyResult $result): void
    {
        $sug = $audit->metaSuggestions()->find($id);
        if (! $sug) {
            return;
        }
        if ($sug->status !== 'pending' && $sug->status !== 'edited') {
            $result->recordSkipped();

            return;
        }

        try {
            DB::transaction(function () use ($sug, $subject, $audit, $userId) {
                $field = $sug->field;
                $value = $sug->suggested_value;
                $previous = null;

                if ($field === 'meta_title' || $field === 'meta_description') {
                    $metadata = $subject->metadata()->firstOrNew([]);
                    $attr = $field === 'meta_title' ? 'title' : 'description';
                    try {
                        $previous = $metadata->getTranslation($attr, $audit->locale);
                    } catch (Throwable) {
                        $previous = null;
                    }
                    $metadata->setTranslation($attr, $audit->locale, $value);
                    $metadata->save();
                } else {
                    if (method_exists($subject, 'getTranslation')) {
                        try {
                            $previous = $subject->getTranslation($field, $audit->locale);
                        } catch (Throwable) {
                            $previous = $subject->{$field} ?? null;
                        }
                    } else {
                        $previous = $subject->{$field} ?? null;
                    }

                    if (method_exists($subject, 'setTranslation')) {
                        $subject->setTranslation($field, $audit->locale, $value);
                    } else {
                        $subject->{$field} = $value;
                    }
                    $subject->save();
                }

                ContentApplyLog::create([
                    'seo_improvement_id' => null,
                    'audit_id' => $audit->id,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'field_key' => 'meta.'.$field,
                    'previous_value' => json_encode($previous),
                    'new_value' => json_encode($value),
                    'applied_by' => $userId,
                    'applied_at' => now(),
                ]);

                $sug->update(['status' => 'applied', 'applied_at' => now()]);
            });
            $result->recordApplied();
        } catch (Throwable $e) {
            $result->recordFailure('meta.'.$sug->field, $e->getMessage());
        }
    }

    protected function applyBlock(SeoAudit $audit, Model $subject, int $id, ?int $userId, SeoAuditApplyResult $result): void
    {
        $sug = $audit->blockSuggestions()->find($id);
        if (! $sug) {
            return;
        }
        if ($sug->status !== 'pending' && $sug->status !== 'edited') {
            $result->recordSkipped();

            return;
        }

        try {
            if (! method_exists($subject, 'customBlocks')) {
                throw new RuntimeException('Subject heeft geen customBlocks relatie');
            }

            $customBlocks = $subject->customBlocks()->firstOrNew([
                'blockable_type' => $subject::class,
                'blockable_id' => $subject->getKey(),
            ]);
            $blocks = [];
            try {
                $blocks = (array) ($customBlocks->getTranslation('blocks', $audit->locale) ?? []);
            } catch (Throwable) {
                $blocks = [];
            }

            $previous = null;
            $newValue = null;
            $logKey = '';

            if ($sug->is_new_block) {
                $data = json_decode($sug->suggested_value, true) ?: [];
                $data = array_merge([
                    'in_container' => true,
                    'top_margin' => true,
                    'bottom_margin' => true,
                ], $data);

                $envelope = ['type' => $sug->block_type, 'data' => $data];
                $blocks[] = $envelope;
                $newValue = $envelope;
                $logKey = 'block.new.'.$sug->block_type;
            } else {
                $idx = $sug->block_index;
                if ($idx === null || ! isset($blocks[$idx])) {
                    $sug->update(['status' => 'failed']);
                    $result->recordFailure("block.{$idx}.{$sug->field_key}", 'Blok niet meer gevonden');

                    return;
                }
                $previous = $blocks[$idx]['data'][$sug->field_key] ?? null;
                $blocks[$idx]['data'][$sug->field_key] = $sug->suggested_value;
                $newValue = $sug->suggested_value;
                $logKey = "block.{$idx}.{$sug->field_key}";
            }

            DB::transaction(function () use ($customBlocks, $audit, $blocks, $subject, $sug, $userId, $previous, $newValue, $logKey) {
                $customBlocks->setTranslation('blocks', $audit->locale, $blocks);
                $customBlocks->save();

                ContentApplyLog::create([
                    'seo_improvement_id' => null,
                    'audit_id' => $audit->id,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'field_key' => $logKey,
                    'previous_value' => json_encode($previous),
                    'new_value' => json_encode($newValue),
                    'applied_by' => $userId,
                    'applied_at' => now(),
                ]);

                $sug->update(['status' => 'applied', 'applied_at' => now()]);
            });

            $result->recordApplied();
        } catch (Throwable $e) {
            $sug->update(['status' => 'failed']);
            $result->recordFailure('block.'.($sug->block_index ?? 'new').'.'.$sug->field_key, $e->getMessage());
        }
    }

    /**
     * @param  array<int>  $ids
     */
    protected function applyFaqs(SeoAudit $audit, Model $subject, array $ids, string $target, ?int $userId, SeoAuditApplyResult $result): void
    {
        if (! method_exists($subject, 'customBlocks')) {
            foreach ($ids as $id) {
                $result->recordFailure("faq.{$id}", 'Subject heeft geen customBlocks relatie');
            }

            return;
        }

        $sugs = $audit->faqSuggestions()->whereIn('id', $ids)
            ->whereIn('status', ['pending', 'edited'])->get();

        if ($sugs->isEmpty()) {
            foreach ($ids as $id) {
                $result->recordSkipped();
            }

            return;
        }

        $items = $sugs->map(fn ($f) => [
            'question' => (string) $f->question,
            'description' => (string) $f->answer,
            'title' => (string) $f->question,
            'content' => (string) $f->answer,
        ])->values()->all();

        try {
            DB::transaction(function () use ($subject, $audit, $sugs, $items, $target, $userId) {
                $customBlocks = $subject->customBlocks()->firstOrNew([
                    'blockable_type' => $subject::class,
                    'blockable_id' => $subject->getKey(),
                ]);
                $blocks = (array) ($customBlocks->getTranslation('blocks', $audit->locale) ?? []);
                $faqTypes = (array) config('dashed-marketing.seo_faq_block_types', ['faq']);

                $previous = null;
                $faqIndex = null;
                foreach ($blocks as $i => $b) {
                    if (in_array($b['type'] ?? null, $faqTypes, true)) {
                        $faqIndex = $i;
                        $previous = $b['data'] ?? [];

                        break;
                    }
                }

                if ($target === 'new' || $faqIndex === null) {
                    $blocks[] = [
                        'type' => $faqTypes[0] ?? 'faq',
                        'data' => [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                            'title' => 'Veelgestelde vragen',
                            'questions' => $items,
                            'faqs' => $items,
                        ],
                    ];
                    $logKey = 'faq.new';
                } else {
                    $current = $blocks[$faqIndex]['data'];
                    $existingQ = (array) ($current['questions'] ?? []);
                    $existingF = (array) ($current['faqs'] ?? []);
                    $current['questions'] = array_merge($existingQ, $items);
                    $current['faqs'] = array_merge($existingF, $items);
                    $blocks[$faqIndex]['data'] = $current;
                    $logKey = "faq.{$faqIndex}";
                }

                $customBlocks->setTranslation('blocks', $audit->locale, $blocks);
                $customBlocks->save();

                ContentApplyLog::create([
                    'seo_improvement_id' => null,
                    'audit_id' => $audit->id,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'field_key' => $logKey,
                    'previous_value' => json_encode($previous),
                    'new_value' => json_encode($items),
                    'applied_by' => $userId,
                    'applied_at' => now(),
                ]);

                foreach ($sugs as $sug) {
                    $sug->update(['status' => 'applied', 'applied_at' => now()]);
                }
            });

            foreach ($sugs as $_) {
                $result->recordApplied();
            }
        } catch (Throwable $e) {
            foreach ($sugs as $sug) {
                $result->recordFailure('faq.'.$sug->id, $e->getMessage());
            }
        }
    }

    protected function updateAuditStatus(SeoAudit $audit, ?int $userId, SeoAuditApplyResult $result): void
    {
        if ($result->applied === 0) {
            return;
        }

        $pendingLeft = $audit->metaSuggestions()->whereIn('status', ['pending', 'edited'])->count()
            + $audit->blockSuggestions()->whereIn('status', ['pending', 'edited'])->count()
            + $audit->faqSuggestions()->whereIn('status', ['pending', 'edited'])->count()
            + $audit->structuredDataSuggestions()->whereIn('status', ['pending', 'edited'])->count();

        $audit->update([
            'status' => $pendingLeft === 0 ? 'fully_applied' : 'partially_applied',
            'applied_at' => $audit->applied_at ?? now(),
            'applied_by' => $audit->applied_by ?? $userId,
        ]);
    }
}
