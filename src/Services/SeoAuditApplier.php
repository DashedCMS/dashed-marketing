<?php

namespace Dashed\DashedMarketing\Services;

use Dashed\DashedCore\Models\CustomStructuredData;
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

        $blockIds = (array) ($selectedIds['blocks'] ?? []);
        $faqIds = array_map('intval', (array) ($selectedIds['faqs'] ?? []));

        $hasNewBlock = ! empty($blockIds) && $audit->blockSuggestions()
            ->whereIn('id', $blockIds)
            ->where('is_new_block', true)
            ->exists();

        if ($hasNewBlock || ! empty($faqIds)) {
            $this->clearAllSubjectBlocks($subject, $audit, $userId);
        }

        foreach ((array) ($selectedIds['meta'] ?? []) as $id) {
            $this->applyMeta($audit, $subject, (int) $id, $userId, $result);
        }

        foreach ($blockIds as $id) {
            $this->applyBlock($audit, $subject, (int) $id, $userId, $result);
        }

        $faqTarget = (string) ($selectedIds['faq_target'] ?? 'existing');
        if (! empty($faqIds)) {
            $this->applyFaqs($audit, $subject, $faqIds, $faqTarget, $userId, $result);
        }

        foreach ((array) ($selectedIds['structured_data'] ?? []) as $id) {
            $this->applyStructuredData($audit, $subject, (int) $id, $userId, $result);
        }

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
        $logs = ContentApplyLog::where('audit_id', $audit->id)
            ->whereNull('reverted_at')
            ->orderByDesc('applied_at')
            ->get();

        foreach ($logs as $log) {
            $this->revertOne($log);
        }
    }

    public function revertOne(ContentApplyLog $log): void
    {
        if (! class_exists($log->subject_type)) {
            return;
        }
        $subject = ($log->subject_type)::find($log->subject_id);
        if (! $subject) {
            return;
        }

        $previous = json_decode($log->previous_value ?? 'null', true);
        $key = $log->field_key;
        $locale = $log->audit?->locale ?? app()->getLocale();

        DB::transaction(function () use ($log, $subject, $key, $previous, $locale) {
            if (str_starts_with($key, 'meta.')) {
                $field = substr($key, 5);
                if ($field === 'meta_title' || $field === 'meta_description') {
                    $attr = $field === 'meta_title' ? 'title' : 'description';
                    $metadata = $subject->metadata;
                    if ($metadata) {
                        $metadata->setTranslation($attr, $locale, (string) ($previous ?? ''));
                        $metadata->save();
                    }
                } else {
                    if (method_exists($subject, 'setTranslation')) {
                        $subject->setTranslation($field, $locale, $previous);
                    } else {
                        $subject->{$field} = $previous;
                    }
                    $subject->save();
                }
            } elseif ($key === 'blocks.wipe' && method_exists($subject, 'customBlocks')) {
                $restored = is_array($previous) ? $previous : [];
                $this->writeBlocks($subject, $locale, $restored);
            } elseif (str_starts_with($key, 'block.') && method_exists($subject, 'customBlocks')) {
                $blocks = $this->loadBlocks($subject, $locale);
                if (str_starts_with($key, 'block.new.')) {
                    $type = substr($key, strlen('block.new.'));
                    for ($i = count($blocks) - 1; $i >= 0; $i--) {
                        if (($blocks[$i]['type'] ?? null) === $type) {
                            array_splice($blocks, $i, 1);

                            break;
                        }
                    }
                } else {
                    [, $idx, $fieldKey] = array_pad(explode('.', $key, 3), 3, null);
                    if (is_numeric($idx) && isset($blocks[(int) $idx])) {
                        $blocks[(int) $idx]['data'][$fieldKey] = $previous;
                    }
                }
                $this->writeBlocks($subject, $locale, $blocks);
            } elseif (str_starts_with($key, 'faq.') && method_exists($subject, 'customBlocks')) {
                $blocks = $this->loadBlocks($subject, $locale);
                if ($key === 'faq.new') {
                    for ($i = count($blocks) - 1; $i >= 0; $i--) {
                        if (in_array($blocks[$i]['type'] ?? null, (array) config('dashed-marketing.seo_faq_block_types', ['faq']), true)) {
                            array_splice($blocks, $i, 1);

                            break;
                        }
                    }
                } else {
                    $idx = (int) substr($key, 4);
                    if (isset($blocks[$idx]) && is_array($previous)) {
                        $blocks[$idx]['data'] = $previous;
                    }
                }
                $this->writeBlocks($subject, $locale, $blocks);
            } elseif (str_starts_with($key, 'structured_data.')) {
                $schema = substr($key, strlen('structured_data.'));
                if ($previous === null) {
                    CustomStructuredData::where('subject_type', $subject::class)
                        ->where('subject_id', $subject->getKey())
                        ->where('schema_type', $schema)
                        ->delete();
                } else {
                    CustomStructuredData::updateOrCreate(
                        ['subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'schema_type' => $schema],
                        ['json_ld' => $previous]
                    );
                }
            }

            $log->update(['reverted_at' => now()]);
        });
    }

    protected function ensureApplyable(SeoAudit $audit): void
    {
        if (! in_array($audit->status, ['ready', 'partially_applied', 'fully_applied'], true)) {
            throw new RuntimeException("Audit is niet applyable in status: {$audit->status}");
        }
    }

    /**
     * Load the current blocks array for a subject + locale.
     *
     * Prefers the legacy translatable `content` column (what the frontend actually
     * renders via `<x-blocks :content="$subject->content">`). Falls back to the
     * `CustomBlock.blocks` relation for subjects that never used the legacy column.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadBlocks(Model $subject, string $locale): array
    {
        $raw = null;

        $translatable = (array) ($subject->translatable ?? []);
        if (in_array('content', $translatable, true) && method_exists($subject, 'getTranslation')) {
            try {
                $raw = $subject->getTranslation('content', $locale);
            } catch (Throwable) {
                $raw = null;
            }
        }

        if (! is_array($raw) || $this->extractBlockItems($raw) === []) {
            if (method_exists($subject, 'customBlocks')) {
                $customBlocks = $subject->customBlocks()->first();
                if ($customBlocks) {
                    try {
                        $raw = $customBlocks->getTranslation('blocks', $locale);
                    } catch (Throwable) {
                        //
                    }
                }
            }
        }

        return $this->extractBlockItems(is_array($raw) ? $raw : []);
    }

    /**
     * Filter a raw blocks container to only the valid, numerically-keyed block
     * envelopes. Drops null entries, `savefirst`-style legacy junk, and
     * named-key dictionary entries (e.g. `top-content`) that belong to other
     * consumers.
     *
     * @param  array<array-key, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function extractBlockItems(array $raw): array
    {
        $out = [];
        foreach ($raw as $key => $value) {
            if (! is_int($key) && ! (is_string($key) && ctype_digit($key))) {
                continue;
            }
            if (! is_array($value)) {
                continue;
            }
            if (! isset($value['type']) || ! array_key_exists('data', $value)) {
                continue;
            }
            $out[] = $value;
        }

        return array_values($out);
    }

    /**
     * Write the blocks array back to the subject. Writes a clean, numerically-
     * indexed array to the legacy translatable `content` column (frontend
     * source of truth) and preserves any non-numeric keys in `CustomBlock.blocks`
     * (e.g. `top-content` on ProductCategory) while replacing the numeric
     * portion with the new blocks and stripping legacy `savefirst`-style junk.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    protected function writeBlocks(Model $subject, string $locale, array $blocks): void
    {
        $blocks = $this->moveFaqBlocksToEnd(array_values($blocks));

        $translatable = (array) ($subject->translatable ?? []);
        if (in_array('content', $translatable, true) && method_exists($subject, 'setTranslation')) {
            $subject->setTranslation('content', $locale, $blocks);
            $subject->save();
        }

        if (method_exists($subject, 'customBlocks')) {
            $customBlocks = $subject->customBlocks()->firstOrNew([
                'blockable_type' => $subject::class,
                'blockable_id' => $subject->getKey(),
            ]);

            $existing = [];
            try {
                $existing = (array) ($customBlocks->getTranslation('blocks', $locale) ?? []);
            } catch (Throwable) {
                $existing = [];
            }

            $preserved = [];
            foreach ($existing as $key => $value) {
                if (is_int($key) || (is_string($key) && ctype_digit($key))) {
                    continue;
                }
                if ($value === null) {
                    continue;
                }
                $preserved[$key] = $value;
            }

            $customBlocks->setTranslation('blocks', $locale, array_merge($blocks, $preserved));
            $customBlocks->save();
        }
    }

    /**
     * Clear all existing blocks on the subject at the start of an apply batch.
     * Logs the previous blocks array in a `ContentApplyLog` so rollbackAudit
     * can restore the pre-apply state. This mirrors an intentionally-destructive
     * "replace entire blocks-list" apply semantic — selected suggestions are
     * the only blocks that remain after `applySelected()` completes.
     */
    protected function clearAllSubjectBlocks(Model $subject, SeoAudit $audit, ?int $userId): void
    {
        $previous = $this->loadBlocks($subject, $audit->locale);

        $this->writeBlocks($subject, $audit->locale, []);

        if (! empty($previous)) {
            ContentApplyLog::create([
                'seo_improvement_id' => null,
                'audit_id' => $audit->id,
                'subject_type' => $subject::class,
                'subject_id' => $subject->getKey(),
                'field_key' => 'blocks.wipe',
                'previous_value' => json_encode($previous),
                'new_value' => json_encode([]),
                'applied_by' => $userId,
                'applied_at' => now(),
            ]);
        }
    }

    /**
     * Ensure FAQ blocks always appear at the end of the blocks list, preserving
     * the relative order of non-FAQ blocks and of FAQ blocks amongst each other.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    protected function moveFaqBlocksToEnd(array $blocks): array
    {
        $faqTypes = (array) config('dashed-marketing.seo_faq_block_types', ['faq']);

        $nonFaq = [];
        $faq = [];
        foreach ($blocks as $block) {
            if (is_array($block) && in_array($block['type'] ?? null, $faqTypes, true)) {
                $faq[] = $block;
            } else {
                $nonFaq[] = $block;
            }
        }

        return array_values(array_merge($nonFaq, $faq));
    }

    /**
     * Convert an HTML string into the TipTap JSON document structure that
     * Filament's RichEditor / Builder-content-block expects.
     *
     * @return array<string, mixed>
     */
    protected function htmlToTiptapDoc(string $html): array
    {
        try {
            $doc = \Filament\Forms\Components\RichEditor\RichContentRenderer::make($html)->toArray();
            if (is_array($doc) && ! empty($doc)) {
                return $doc;
            }
        } catch (Throwable) {
            //
        }

        return ['type' => 'doc', 'content' => []];
    }

    protected function applyMeta(SeoAudit $audit, Model $subject, int $id, ?int $userId, SeoAuditApplyResult $result): void
    {
        $sug = $audit->metaSuggestions()->find($id);
        if (! $sug) {
            return;
        }
        if (in_array($sug->status, ['rejected', 'failed'], true)) {
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
        if (in_array($sug->status, ['rejected', 'failed'], true)) {
            $result->recordSkipped();

            return;
        }

        try {
            if (! method_exists($subject, 'customBlocks')) {
                throw new RuntimeException('Subject heeft geen customBlocks relatie');
            }

            $blocks = $this->loadBlocks($subject, $audit->locale);

            $previous = null;
            $newValue = null;
            $logKey = '';

            if ($sug->is_new_block) {
                $isOutline = is_string($sug->block_key) && str_starts_with($sug->block_key, 'outline.');

                if ($isOutline) {
                    $sort = (int) substr($sug->block_key, strlen('outline.'));
                    $data = [
                        'content' => $this->htmlToTiptapDoc((string) $sug->suggested_value),
                        'full-width' => false,
                        'top_margin' => $sort === 0,
                        'in_container' => true,
                        'bottom_margin' => true,
                    ];
                } else {
                    $decoded = json_decode((string) $sug->suggested_value, true);
                    if (is_array($decoded)) {
                        $data = array_merge([
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                        ], $decoded);
                    } else {
                        $data = [
                            'in_container' => true,
                            'top_margin' => true,
                            'bottom_margin' => true,
                            'content' => (string) $sug->suggested_value,
                        ];
                    }
                }

                $envelope = ['type' => $sug->block_type, 'data' => $data];

                $overwriteIndex = $sug->applied_block_index;
                $canOverwrite = $overwriteIndex !== null
                    && isset($blocks[$overwriteIndex])
                    && ($blocks[$overwriteIndex]['type'] ?? null) === $sug->block_type;

                if ($canOverwrite) {
                    $previous = $blocks[$overwriteIndex];
                    $blocks[$overwriteIndex] = $envelope;
                    $appliedIndex = $overwriteIndex;
                    $logKey = 'block.'.$appliedIndex.'.'.$sug->block_type;
                } else {
                    $blocks[] = $envelope;
                    $appliedIndex = array_key_last($blocks);
                    $logKey = 'block.new.'.$sug->block_type;
                }

                $newValue = $envelope;
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

            $appliedIndexForClosure = $sug->is_new_block ? ($appliedIndex ?? null) : null;

            DB::transaction(function () use ($audit, $blocks, $subject, $sug, $userId, $previous, $newValue, $logKey, $appliedIndexForClosure) {
                $this->writeBlocks($subject, $audit->locale, $blocks);

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

                $updates = ['status' => 'applied', 'applied_at' => now()];
                if ($appliedIndexForClosure !== null) {
                    $updates['applied_block_index'] = $appliedIndexForClosure;
                }
                $sug->update($updates);
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
            ->whereNotIn('status', ['rejected', 'failed'])->get();

        if ($sugs->isEmpty()) {
            foreach ($ids as $id) {
                $result->recordSkipped();
            }

            return;
        }

        $items = $sugs->map(function ($f) {
            $answer = (string) $f->answer;
            $answerDoc = $this->htmlToTiptapDoc($answer);

            return [
                'question' => (string) $f->question,
                'description' => $answerDoc,
                'title' => (string) $f->question,
                'content' => $answerDoc,
            ];
        })->values()->all();

        try {
            DB::transaction(function () use ($subject, $audit, $sugs, $items, $target, $userId) {
                $blocks = $this->loadBlocks($subject, $audit->locale);
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

                $this->writeBlocks($subject, $audit->locale, $blocks);

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

    protected function applyStructuredData(SeoAudit $audit, Model $subject, int $id, ?int $userId, SeoAuditApplyResult $result): void
    {
        $sug = $audit->structuredDataSuggestions()->find($id);
        if (! $sug) {
            return;
        }
        if (in_array($sug->status, ['rejected', 'failed'], true)) {
            $result->recordSkipped();

            return;
        }

        try {
            DB::transaction(function () use ($audit, $subject, $sug, $userId) {
                $previous = CustomStructuredData::where('subject_type', $subject::class)
                    ->where('subject_id', $subject->getKey())
                    ->where('schema_type', $sug->schema_type)
                    ->first()?->json_ld;

                CustomStructuredData::updateOrCreate(
                    ['subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'schema_type' => $sug->schema_type],
                    ['json_ld' => $sug->json_ld]
                );

                ContentApplyLog::create([
                    'seo_improvement_id' => null,
                    'audit_id' => $audit->id,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'field_key' => 'structured_data.'.$sug->schema_type,
                    'previous_value' => json_encode($previous),
                    'new_value' => json_encode($sug->json_ld),
                    'applied_by' => $userId,
                    'applied_at' => now(),
                ]);

                $sug->update(['status' => 'applied', 'applied_at' => now()]);
            });

            $result->recordApplied();
        } catch (Throwable $e) {
            $result->recordFailure('structured_data.'.$sug->schema_type, $e->getMessage());
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
