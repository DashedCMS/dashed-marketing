<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedMarketing\Models\ContentApplyLog;
use Dashed\DashedMarketing\Models\SeoAudit;
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

        // Block, FAQ, and structured data appliers land in Tasks 4.3-4.5.

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
