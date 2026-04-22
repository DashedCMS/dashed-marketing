<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
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
        Ai::json(SeoAuditPromptBuilder::pageAnalysis($context));
    }

    protected function suggestKeywords(SeoAudit $audit, array $context): void
    {
        Ai::json(SeoAuditPromptBuilder::keywords($context));
    }

    protected function suggestMeta(SeoAudit $audit, array $context): void
    {
        Ai::json(SeoAuditPromptBuilder::meta($context));
    }

    protected function suggestBlockRewrites(SeoAudit $audit, array $context): void
    {
        Ai::json(SeoAuditPromptBuilder::blocks($context));
    }

    protected function suggestFaqs(SeoAudit $audit, array $context): void
    {
        Ai::json(SeoAuditPromptBuilder::faqs($context));
    }

    protected function suggestStructuredData(SeoAudit $audit, array $context): void
    {
        Ai::json(SeoAuditPromptBuilder::structuredData($context));
    }

    protected function suggestInternalLinks(SeoAudit $audit, array $context): void
    {
        Ai::json(SeoAuditPromptBuilder::internalLinks($context));
    }
}
