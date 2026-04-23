<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Services\LinkCandidatesService;
use Dashed\DashedMarketing\Services\Prompts\SeoAuditPromptBuilder;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSeoAuditJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 600;

    public function __construct(
        public string $subjectType,
        public int|string $subjectId,
        public ?int $userId = null,
        public ?string $instruction = null,
    ) {}

    public function handle(): void
    {
        $audit = $this->bootstrapAudit();
        if ($audit === null) {
            return;
        }

        try {
            $context = $this->buildContext($audit);
        } catch (Throwable $e) {
            $audit->update([
                'status' => 'failed',
                'error_message' => 'Context build failed: '.$e->getMessage(),
            ]);

            return;
        }

        $this->runStep($audit, 'page_analysis', fn () => $this->analysePage($audit, $context));
        $this->runStep($audit, 'keywords', fn () => $this->suggestKeywords($audit, $context));
        $this->runStep($audit, 'meta', fn () => $this->suggestMeta($audit, $context));
        $this->runStep($audit, 'blocks', fn () => $this->suggestBlockRewrites($audit, $context));
        $this->runStep($audit, 'faqs', fn () => $this->suggestFaqs($audit, $context));
        $this->runStep($audit, 'structured_data', fn () => $this->suggestStructuredData($audit, $context));
        $this->runStep($audit, 'internal_links', fn () => $this->suggestInternalLinks($audit, $context));

        $audit->refresh();
        $audit->update([
            'status' => 'ready',
            'progress_message' => null,
            'overall_score' => $this->calcScore($audit),
            'score_breakdown' => $audit->score_breakdown ?? [],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        SeoAudit::where('subject_type', $this->subjectType)
            ->where('subject_id', $this->subjectId)
            ->where('status', 'analyzing')
            ->update([
                'status' => 'failed',
                'progress_message' => null,
                'error_message' => 'Job gefaald: '.$exception->getMessage(),
            ]);
    }

    protected function bootstrapAudit(): ?SeoAudit
    {
        if (! class_exists($this->subjectType)) {
            return null;
        }
        $subject = ($this->subjectType)::find($this->subjectId);
        if (! $subject) {
            return null;
        }

        SeoAudit::query()
            ->where('subject_type', $this->subjectType)
            ->where('subject_id', $this->subjectId)
            ->whereNotIn('status', ['archived', 'failed'])
            ->update(['status' => 'archived', 'archived_at' => now()]);

        return SeoAudit::create([
            'subject_type' => $this->subjectType,
            'subject_id' => (int) $this->subjectId,
            'status' => 'analyzing',
            'locale' => $subject->locale ?? app()->getLocale(),
            'instruction' => $this->instruction,
            'created_by' => $this->userId,
            'progress_message' => 'Audit start',
            'score_breakdown' => [],
        ]);
    }

    protected function buildContext(SeoAudit $audit): array
    {
        $subject = $audit->subject;
        $locale = $audit->locale;

        $name = $this->readTranslatable($subject, 'name', $locale)
            ?? $this->readTranslatable($subject, 'title', $locale)
            ?? '';
        $slug = $this->readTranslatable($subject, 'slug', $locale) ?? '';
        $url = '';
        if (method_exists($subject, 'getUrl')) {
            try {
                $url = (string) $subject->getUrl();
            } catch (Throwable) {
                $url = '';
            }
        }

        $metaTitle = '';
        $metaDescription = '';
        if (method_exists($subject, 'metadata') && $subject->metadata) {
            try {
                $metaTitle = (string) $subject->metadata->getTranslation('title', $locale);
                $metaDescription = (string) $subject->metadata->getTranslation('description', $locale);
            } catch (Throwable) {
                //
            }
        }

        $blocks = [];
        if (method_exists($subject, 'customBlocks') && $subject->customBlocks) {
            try {
                $raw = $subject->customBlocks->getTranslation('blocks', $locale);
                if (is_string($raw)) {
                    $raw = json_decode($raw, true) ?: [];
                }
                foreach ((array) $raw as $i => $b) {
                    $blocks[] = [
                        'index' => $i,
                        'type' => $b['type'] ?? 'unknown',
                        'data' => $b['data'] ?? [],
                    ];
                }
            } catch (Throwable) {
                //
            }
        }

        $brand = '';
        try {
            $brand = app(SocialContextBuilder::class)->build('seo');
        } catch (Throwable) {
            //
        }

        $routePool = [];
        try {
            $routePool = app(LinkCandidatesService::class)->allForLocale($locale, 200);
        } catch (Throwable) {
            //
        }

        $existingFaqs = [];
        foreach ($blocks as $b) {
            $type = $b['type'];
            if (in_array($type, (array) config('dashed-marketing.seo_faq_block_types', ['faq']), true)) {
                $items = $b['data']['questions'] ?? $b['data']['faqs'] ?? [];
                foreach ((array) $items as $item) {
                    $existingFaqs[] = [
                        'question' => $item['question'] ?? $item['title'] ?? '',
                        'answer' => $item['description'] ?? $item['content'] ?? $item['answer'] ?? '',
                    ];
                }
            }
        }

        return [
            'subject' => [
                'type' => class_basename($this->subjectType),
                'id' => $audit->subject_id,
                'name' => $name,
                'slug' => $slug,
                'url' => $url,
            ],
            'locale' => $locale,
            'brand' => $brand,
            'user_instruction' => $this->instruction,
            'current_meta' => ['title' => $metaTitle, 'description' => $metaDescription],
            'current_blocks' => $blocks,
            'block_whitelist' => (array) config('dashed-marketing.seo_block_whitelist', []),
            'route_pool' => $routePool,
            'existing_faqs' => $existingFaqs,
        ];
    }

    protected function runStep(SeoAudit $audit, string $step, callable $fn): void
    {
        $audit->update(['progress_message' => "Stap: {$step}"]);
        try {
            $fn();
            $this->markStep($audit, $step, true);
        } catch (Throwable $e) {
            Log::error("GenerateSeoAuditJob: {$step} failed", [
                'audit_id' => $audit->id,
                'error' => $e->getMessage(),
            ]);
            $this->markStep($audit, $step, false);
        }
    }

    protected function markStep(SeoAudit $audit, string $step, bool $ok): void
    {
        $breakdown = $audit->score_breakdown ?? [];
        if (! $ok) {
            $breakdown[$step] = null;
        }
        $audit->update(['score_breakdown' => $breakdown]);
    }

    protected function calcScore(SeoAudit $audit): int
    {
        $analysis = $audit->pageAnalysis;

        $pieces = [];
        $pieces['content'] = $analysis->readability_score ?? 60;
        $pieces['meta'] = max(20, 100 - ($audit->metaSuggestions()->count() * 10));
        $pieces['structure'] = max(20, 100 - ($audit->blockSuggestions()->count() * 5));
        $pieces['faqs'] = $audit->faqSuggestions()->count() === 0 ? 85 : 60;
        $pieces['schema'] = $audit->structuredDataSuggestions()->count() === 0 ? 90 : 50;
        $pieces['links'] = $audit->internalLinkSuggestions()->count() >= 3 ? 90 : 60;

        $audit->score_breakdown = $pieces;
        $audit->save();

        return (int) round(array_sum($pieces) / count($pieces));
    }

    private function readTranslatable($model, string $attr, string $locale): ?string
    {
        if (method_exists($model, 'getTranslation')) {
            try {
                $v = $model->getTranslation($attr, $locale);

                return is_string($v) ? $v : null;
            } catch (Throwable) {
                //
            }
        }
        $v = $model->{$attr} ?? null;
        if (is_array($v)) {
            return (string) ($v[$locale] ?? reset($v) ?? '');
        }

        return is_string($v) ? $v : null;
    }

    // Step stubs — implemented in Tasks 3.4-3.10. For now they just consume the Ai::json call
    // so the test that stubs it 7 times works and the skeleton is fully exercised.

    protected function analysePage(SeoAudit $audit, array $context): void
    {
        $response = Ai::json(SeoAuditPromptBuilder::pageAnalysis($context)) ?? [];

        $audit->pageAnalysis()->updateOrCreate(
            ['audit_id' => $audit->id],
            [
                'headings_structure' => is_array($response['headings_structure'] ?? null) ? $response['headings_structure'] : null,
                'content_length' => is_numeric($response['content_length'] ?? null) ? (int) $response['content_length'] : null,
                'keyword_density' => is_array($response['keyword_density'] ?? null) ? $response['keyword_density'] : null,
                'alt_text_coverage' => is_array($response['alt_text_coverage'] ?? null) ? $response['alt_text_coverage'] : null,
                'readability_score' => is_numeric($response['readability_score'] ?? null)
                    ? (int) max(0, min(100, $response['readability_score']))
                    : null,
                'notes' => is_string($response['notes'] ?? null) ? $response['notes'] : null,
            ]
        );

        if (! empty($response['summary']) && empty($audit->analysis_summary)) {
            $audit->update(['analysis_summary' => $response['summary']]);
        }
    }

    protected function suggestKeywords(SeoAudit $audit, array $context): void
    {
        $audit->keywords()->delete();
        $types = ['primary', 'secondary', 'longtail', 'lsi', 'gap'];
        $volumes = ['high', 'medium', 'low'];
        $intents = ['informational', 'commercial', 'transactional', 'navigational'];
        $seeded = [];

        // Primary source: non-rejected keywords from the keyword-research table for this locale.
        // These are manually curated, so we trust them over AI invention.
        try {
            $research = Keyword::query()
                ->where('locale', $audit->locale)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'rejected');
                })
                ->get();

            foreach ($research as $kw) {
                $type = in_array($kw->type, $types, true) ? $kw->type : 'secondary';
                $volume = in_array($kw->volume_indication, $volumes, true) ? $kw->volume_indication : null;
                $notes = $kw->volume_exact ? "Uit zoekwoordonderzoek, {$kw->volume_exact}/mnd" : 'Uit zoekwoordonderzoek';

                $audit->keywords()->create([
                    'keyword' => (string) $kw->keyword,
                    'type' => $type,
                    'intent' => null,
                    'volume_indication' => $volume,
                    'priority' => 'medium',
                    'notes' => $notes,
                ]);

                $seeded[mb_strtolower(trim((string) $kw->keyword))] = true;
            }
        } catch (\Throwable) {
            //
        }

        // Secondary: AI augments with LSI + gap keywords that aren't in the research.
        // The prompt knows which keywords are already seeded so it can focus on gaps.
        $response = Ai::json(SeoAuditPromptBuilder::keywords(array_merge($context, [
            'seeded_keywords' => array_values(array_map('trim', array_keys($seeded))),
        ]))) ?? [];

        foreach ((array) ($response['suggestions'] ?? []) as $k) {
            if (! is_array($k)) {
                continue;
            }
            $keyword = trim((string) ($k['keyword'] ?? ''));
            $type = $k['type'] ?? null;
            if ($keyword === '' || ! in_array($type, $types, true)) {
                continue;
            }
            // Skip duplicates of the seeded keywords.
            if (isset($seeded[mb_strtolower($keyword)])) {
                continue;
            }
            // Only accept AI augmentation for LSI + gap types. Primary/secondary/longtail
            // come from the curated research and should not be inflated by AI.
            if (! in_array($type, ['lsi', 'gap'], true)) {
                continue;
            }

            $audit->keywords()->create([
                'keyword' => $keyword,
                'type' => $type,
                'intent' => in_array($k['intent'] ?? null, $intents, true) ? $k['intent'] : null,
                'volume_indication' => in_array($k['volume_indication'] ?? null, $volumes, true) ? $k['volume_indication'] : null,
                'priority' => $this->normalisePriority($k['priority'] ?? null),
                'notes' => is_string($k['notes'] ?? null) ? $k['notes'] : null,
            ]);
        }
    }

    protected function suggestMeta(SeoAudit $audit, array $context): void
    {
        $response = Ai::json(SeoAuditPromptBuilder::meta($context)) ?? [];
        $allowed = ['name', 'slug', 'excerpt', 'meta_title', 'meta_description'];

        foreach ((array) ($response['suggestions'] ?? []) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $field = $s['field'] ?? null;
            $value = $s['suggested_value'] ?? null;
            if (! in_array($field, $allowed, true) || ! is_string($value) || trim($value) === '') {
                continue;
            }

            $current = $this->currentMetaValue($audit, $field, $context);

            $audit->metaSuggestions()->updateOrCreate(
                ['field' => $field],
                [
                    'current_value' => $current,
                    'suggested_value' => trim($value),
                    'reason' => $s['reason'] ?? null,
                    'priority' => $this->normalisePriority($s['priority'] ?? null),
                    'status' => 'pending',
                ]
            );
        }

        if (! empty($response['summary']) && empty($audit->analysis_summary)) {
            $audit->update(['analysis_summary' => $response['summary']]);
        }
    }

    private function currentMetaValue(SeoAudit $audit, string $field, array $context): ?string
    {
        if ($field === 'meta_title') {
            return $context['current_meta']['title'] ?? null;
        }
        if ($field === 'meta_description') {
            return $context['current_meta']['description'] ?? null;
        }
        $subject = $audit->subject;
        if (! $subject) {
            return null;
        }

        return $this->readTranslatable($subject, $field, $audit->locale);
    }

    private function normalisePriority(?string $p): string
    {
        return in_array($p, ['high', 'medium', 'low'], true) ? $p : 'medium';
    }

    protected function suggestBlockRewrites(SeoAudit $audit, array $context): void
    {
        $response = Ai::json(SeoAuditPromptBuilder::blocks($context)) ?? [];
        $whitelist = $context['block_whitelist'];
        $blocksByIndex = [];
        foreach ($context['current_blocks'] as $b) {
            $blocksByIndex[$b['index']] = $b;
        }

        $audit->blockSuggestions()->delete();

        foreach ((array) ($response['suggestions'] ?? []) as $s) {
            if (! is_array($s)) {
                continue;
            }

            $isNew = (bool) ($s['is_new_block'] ?? false);
            $blockType = (string) ($s['block_type'] ?? '');
            $fieldKey = (string) ($s['field_key'] ?? '');
            $suggested = $s['suggested_value'] ?? null;

            if ($blockType === '' || $suggested === null) {
                continue;
            }

            $allowed = (array) ($whitelist[$blockType] ?? []);
            if (! $isNew && ! in_array($fieldKey, $allowed, true)) {
                continue;
            }

            $blockIndex = $s['block_index'] ?? null;
            $current = null;
            if (! $isNew) {
                if (! is_numeric($blockIndex) || ! isset($blocksByIndex[(int) $blockIndex])) {
                    continue;
                }
                $blockIndex = (int) $blockIndex;
                $existingBlock = $blocksByIndex[$blockIndex];
                $current = $existingBlock['data'][$fieldKey] ?? null;
                if (is_array($current)) {
                    $current = json_encode($current, JSON_UNESCAPED_UNICODE);
                }
            } else {
                $blockIndex = null;
                if (! is_string($suggested) || json_decode($suggested, true) === null) {
                    continue;
                }
                $fieldKey = $fieldKey !== '' ? $fieldKey : '_new';
            }

            $audit->blockSuggestions()->create([
                'block_index' => $blockIndex,
                'block_key' => null,
                'block_type' => $blockType,
                'field_key' => $fieldKey,
                'is_new_block' => $isNew,
                'current_value' => $current !== null ? (string) $current : null,
                'suggested_value' => is_string($suggested) ? $suggested : json_encode($suggested, JSON_UNESCAPED_UNICODE),
                'reason' => is_string($s['reason'] ?? null) ? $s['reason'] : null,
                'priority' => $this->normalisePriority($s['priority'] ?? null),
                'status' => 'pending',
            ]);
        }
    }

    protected function suggestFaqs(SeoAudit $audit, array $context): void
    {
        $response = Ai::json(SeoAuditPromptBuilder::faqs($context)) ?? [];

        $audit->faqSuggestions()->delete();

        foreach ((array) ($response['suggestions'] ?? []) as $i => $f) {
            if (! is_array($f)) {
                continue;
            }
            $q = trim((string) ($f['question'] ?? ''));
            $a = trim((string) ($f['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }

            $audit->faqSuggestions()->create([
                'sort_order' => $i,
                'question' => $q,
                'answer' => $a,
                'target_keyword' => is_string($f['target_keyword'] ?? null) ? $f['target_keyword'] : null,
                'priority' => $this->normalisePriority($f['priority'] ?? null),
                'status' => 'pending',
            ]);
        }
    }

    protected function suggestStructuredData(SeoAudit $audit, array $context): void
    {
        $response = Ai::json(SeoAuditPromptBuilder::structuredData($context)) ?? [];

        $audit->structuredDataSuggestions()->delete();

        foreach ((array) ($response['suggestions'] ?? []) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $schema = trim((string) ($s['schema_type'] ?? ''));
            $jsonLd = (string) ($s['json_ld'] ?? '');

            if ($schema === '' || $jsonLd === '') {
                continue;
            }

            if (json_decode($jsonLd, true) === null) {
                continue;
            }

            $audit->structuredDataSuggestions()->updateOrCreate(
                ['schema_type' => $schema],
                [
                    'json_ld' => $jsonLd,
                    'reason' => is_string($s['reason'] ?? null) ? $s['reason'] : null,
                    'priority' => $this->normalisePriority($s['priority'] ?? null),
                    'status' => 'pending',
                ]
            );
        }
    }

    protected function suggestInternalLinks(SeoAudit $audit, array $context): void
    {
        $response = Ai::json(SeoAuditPromptBuilder::internalLinks($context)) ?? [];

        $validUrls = array_flip(array_column($context['route_pool'], 'url'));
        $audit->internalLinkSuggestions()->delete();

        foreach ((array) ($response['suggestions'] ?? []) as $s) {
            if (! is_array($s)) {
                continue;
            }
            $anchor = trim((string) ($s['anchor_text'] ?? ''));
            $url = trim((string) ($s['target_url'] ?? ''));
            $context_desc = trim((string) ($s['context_description'] ?? ''));

            if ($anchor === '' || $url === '' || ! isset($validUrls[$url])) {
                continue;
            }

            $audit->internalLinkSuggestions()->create([
                'anchor_text' => $anchor,
                'target_url' => $url,
                'target_subject_type' => is_string($s['target_subject_type'] ?? null) ? $s['target_subject_type'] : null,
                'target_subject_id' => is_numeric($s['target_subject_id'] ?? null) ? (int) $s['target_subject_id'] : null,
                'context_description' => $context_desc,
                'reason' => is_string($s['reason'] ?? null) ? $s['reason'] : null,
                'priority' => $this->normalisePriority($s['priority'] ?? null),
                'status' => 'pending',
            ]);
        }
    }
}
