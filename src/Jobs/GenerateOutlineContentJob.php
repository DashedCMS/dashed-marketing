<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\SeoAudit;
use Dashed\DashedMarketing\Services\Prompts\SeoAuditPromptBuilder;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateOutlineContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 600;

    public function __construct(public int $auditId) {}

    public function handle(): void
    {
        $audit = SeoAudit::with('outline')->find($this->auditId);
        if ($audit === null) {
            return;
        }

        $outline = $audit->outline;
        if ($outline === null) {
            return;
        }

        $headings = is_array($outline->headings) ? $outline->headings : [];
        if (empty($headings)) {
            $outline->update(['content_generating_at' => null]);

            return;
        }

        $audit->blockSuggestions()->where('is_new_block', true)->delete();

        $ctx = $this->buildContext($audit);
        $sort = 0;

        foreach ($headings as $heading) {
            $level = (int) ($heading['level'] ?? 2);
            $text = trim((string) ($heading['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (\Dashed\DashedMarketing\Support\FaqHeadingDetector::isFaq($text)) {
                continue;
            }

            $response = [];
            try {
                $response = Ai::json(
                    SeoAuditPromptBuilder::outlineContent(
                        $ctx,
                        $text,
                        $level,
                        (string) ($outline->h1 ?? ''),
                        (string) ($outline->summary ?? ''),
                    )
                ) ?? [];
            } catch (\Throwable $e) {
                $response = [];
            }

            $body = '';
            foreach (['body', 'html', 'content', 'text'] as $key) {
                if (! empty($response[$key]) && is_string($response[$key])) {
                    $body = trim($response[$key]);

                    break;
                }
            }

            $tag = 'h'.$level;
            $html = "<{$tag}>".e($text)."</{$tag}>".$body;

            $audit->blockSuggestions()->create([
                'block_index' => null,
                'block_key' => 'outline.'.$sort,
                'block_type' => 'content',
                'field_key' => '_new',
                'is_new_block' => true,
                'suggested_value' => $html,
                'reason' => 'Op basis van outline heading: '.$text,
                'priority' => 'medium',
                'status' => 'pending',
            ]);

            $sort++;
        }

        $outline->update([
            'content_generated_at' => now(),
            'content_generating_at' => null,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateOutlineContentJob: final failure', [
            'audit_id' => $this->auditId,
            'error' => $exception->getMessage(),
        ]);

        $audit = SeoAudit::with('outline')->find($this->auditId);
        $audit?->outline?->update(['content_generating_at' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildContext(SeoAudit $audit): array
    {
        $subject = $audit->subject;
        $locale = $audit->locale;

        $name = '';
        if ($subject) {
            $raw = $subject->name ?? $subject->title ?? null;
            if (is_array($raw)) {
                $name = (string) ($raw[$locale] ?? reset($raw) ?? '');
            } else {
                $name = (string) $raw;
            }
        }

        $url = '';
        if ($subject && method_exists($subject, 'getUrl')) {
            try {
                $url = (string) $subject->getUrl();
            } catch (\Throwable) {
                //
            }
        }

        $brand = '';
        try {
            $brand = app(SocialContextBuilder::class)->build('seo');
        } catch (\Throwable) {
            //
        }

        $seededKeywords = [];
        try {
            $seededKeywords = \Dashed\DashedMarketing\Models\Keyword::query()
                ->where('locale', $locale)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'rejected');
                })
                ->pluck('keyword')
                ->map(fn ($k) => (string) $k)
                ->all();
        } catch (\Throwable) {
            //
        }

        return [
            'subject' => [
                'type' => class_basename($audit->subject_type),
                'id' => $audit->subject_id,
                'name' => $name,
                'url' => $url,
            ],
            'locale' => $locale,
            'brand' => $brand,
            'user_instruction' => $audit->instruction,
            'seeded_keywords' => $seededKeywords,
        ];
    }
}
