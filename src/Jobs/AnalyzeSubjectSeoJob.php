<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeSubjectSeoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 20;

    public int $timeout = 90;

    public function __construct(
        public string $subjectType,
        public int|string $subjectId,
        public ?int $userId = null,
        public ?string $instruction = null,
    ) {}

    public function handle(): void
    {
        if (! class_exists($this->subjectType)) {
            return;
        }

        $subject = ($this->subjectType)::find($this->subjectId);
        if ($subject === null) {
            return;
        }

        $improvement = SeoImprovement::updateOrCreate(
            [
                'subject_type' => $this->subjectType,
                'subject_id' => (int) $this->subjectId,
            ],
            [
                'status' => 'analyzing',
                'progress_message' => 'AI analyseert het huidige content',
                'error_message' => null,
                'field_proposals' => null,
                'block_proposals' => null,
                'block_proposals_status' => null,
                'analysis_summary' => null,
                'applied_at' => null,
                'applied_by' => null,
                'created_by' => $this->userId,
            ]
        );

        $response = Ai::json($this->buildPrompt($subject));
        if ($response === null) {
            throw new \RuntimeException('Ai::json returned null');
        }

        $improvement->update([
            'status' => 'ready',
            'progress_message' => null,
            'field_proposals' => $this->extractFieldProposals($response),
            'analysis_summary' => (string) ($response['summary'] ?? $response['analysis'] ?? ''),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeSubjectSeoJob: final failure after retries', [
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'error' => $exception->getMessage(),
        ]);

        SeoImprovement::where('subject_type', $this->subjectType)
            ->where('subject_id', $this->subjectId)
            ->update([
                'status' => 'failed',
                'progress_message' => null,
                'error_message' => 'AI-analyse gefaald na '.$this->tries.' pogingen: '.$exception->getMessage(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function extractFieldProposals(array $response): array
    {
        $fields = $response['fields'] ?? $response['field_proposals'] ?? [];
        if (! is_array($fields)) {
            return [];
        }

        $allowed = ['name', 'title', 'slug', 'excerpt', 'meta_title', 'meta_description'];
        $out = [];

        foreach ($allowed as $key) {
            $value = $fields[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$key] = trim($value);
            }
        }

        return $out;
    }

    protected function buildPrompt($subject): string
    {
        $locale = app()->getLocale();

        $title = $this->readTranslatable($subject, 'name', $locale)
            ?? $this->readTranslatable($subject, 'title', $locale)
            ?? '';
        $slug = $this->readTranslatable($subject, 'slug', $locale) ?? '';
        $excerpt = $this->readTranslatable($subject, 'excerpt', $locale) ?? '';

        $metaTitle = '';
        $metaDescription = '';
        if (method_exists($subject, 'metadata') && $subject->metadata) {
            try {
                $metaTitle = (string) $subject->metadata->getTranslation('title', $locale);
                $metaDescription = (string) $subject->metadata->getTranslation('description', $locale);
            } catch (\Throwable) {
                //
            }
        }

        $contentSummary = $this->summarizeContent($subject, $locale);

        $instruction = $this->instruction
            ? "Aanvullende instructie van de gebruiker: {$this->instruction}\n\n"
            : '';

        $subjectKind = class_basename($subject);

        return <<<TXT
Analyseer het volgende {$subjectKind} op SEO en stel verbeteringen voor.

{$instruction}Huidige velden (locale: {$locale}):
- name / title: {$title}
- slug: {$slug}
- excerpt: {$excerpt}
- meta_title: {$metaTitle}
- meta_description: {$metaDescription}

Content samenvatting:
{$contentSummary}

Regels (hard):
- meta_title: 50 tot 60 tekens, bevat primair keyword, geen merknaam tenzij relevant.
- meta_description: 140 tot 160 tekens, concreet, eindigt met subtiele CTA.
- slug: kort, lowercase, woorden gescheiden met -, alleen voorstellen als de huidige slug echt slechter is.
- Geen em-dashes, geen emoji, geen AI-clichés, actieve vorm, "je"-vorm.
- Als een veld al prima is, laat het WEG uit de output in plaats van een marginale variant te geven.

Retourneer JSON:
{"summary": "korte Nederlandse analyse in 2-3 zinnen", "fields": {"meta_title": "...", "meta_description": "...", "name": "...", "slug": "...", "excerpt": "..."}}
TXT;
    }

    private function readTranslatable($model, string $attr, string $locale): ?string
    {
        if (method_exists($model, 'getTranslation')) {
            try {
                $v = $model->getTranslation($attr, $locale);

                return is_string($v) ? $v : null;
            } catch (\Throwable) {
                //
            }
        }

        $value = $model->{$attr} ?? null;
        if (is_array($value)) {
            return (string) ($value[$locale] ?? reset($value) ?? '');
        }

        return is_string($value) ? $value : null;
    }

    private function summarizeContent($subject, string $locale): string
    {
        if (method_exists($subject, 'customBlocks') && $subject->customBlocks) {
            try {
                $blocks = $subject->customBlocks->getTranslation('blocks', $locale);
                if (is_string($blocks)) {
                    $blocks = json_decode($blocks, true) ?: [];
                }
                if (is_array($blocks) && ! empty($blocks)) {
                    return mb_substr(strip_tags((string) json_encode($blocks)), 0, 1500);
                }
            } catch (\Throwable) {
                //
            }
        }

        $content = $this->readTranslatable($subject, 'content', $locale) ?? '';
        if ($content !== '') {
            return mb_substr(strip_tags($content), 0, 1500);
        }

        return '(geen content gevonden om samen te vatten)';
    }
}
