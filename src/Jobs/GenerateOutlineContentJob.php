<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\SeoAudit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Support\FaqHeadingDetector;
use Dashed\DashedMarketing\Services\SocialContextBuilder;
use Dashed\DashedMarketing\Services\Prompts\SeoAuditPromptBuilder;

class GenerateOutlineContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 600;

    public function __construct(public int $auditId)
    {
    }

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

        // Tracking-map: suggestion-id => ['heading','level','body']. Wordt
        // gebruikt in de dedupe-pass om gedupliceerde zinnen tussen blokken
        // op te sporen en die blokken eenmalig opnieuw te genereren met een
        // expliciete "vermijd"-lijst.
        $generated = [];

        foreach ($headings as $heading) {
            $level = (int) ($heading['level'] ?? 2);
            $text = trim((string) ($heading['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (FaqHeadingDetector::isFaq($text)) {
                continue;
            }

            $body = $this->generateBody($ctx, $text, $level, (string) ($outline->h1 ?? ''), (string) ($outline->summary ?? ''));

            $tag = 'h'.$level;
            $html = "<{$tag}>".e($text)."</{$tag}>".$body;

            $suggestion = $audit->blockSuggestions()->create([
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

            $generated[$suggestion->id] = [
                'heading' => $text,
                'level' => $level,
                'body' => $body,
            ];

            $sort++;
        }

        $this->dedupeAcrossBlocks($audit, $outline, $ctx, $generated);

        $outline->update([
            'content_generated_at' => now(),
            'content_generating_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<int, string>  $avoidPhrases
     */
    protected function generateBody(array $ctx, string $heading, int $level, string $h1, string $summary, array $avoidPhrases = []): string
    {
        try {
            $response = Ai::json(
                SeoAuditPromptBuilder::outlineContent($ctx, $heading, $level, $h1, $summary, $avoidPhrases)
            ) ?? [];
        } catch (\Throwable $e) {
            $response = [];
        }

        foreach (['body', 'html', 'content', 'text'] as $key) {
            if (! empty($response[$key]) && is_string($response[$key])) {
                return trim($response[$key]);
            }
        }

        return '';
    }

    /**
     * Pakt alle gegenereerde blok-bodies, zoekt zinnen die meermaals
     * voorkomen tussen verschillende blokken, en hergenereert alleen die
     * blokken met de duplicaten als expliciete "niet herhalen"-lijst.
     * Eén pass: als regeneratie nog duplicaten bevat blijft het zo, want
     * een ongelimiteerde loop zou kunnen vastlopen.
     *
     * @param  array<int, array{heading: string, level: int, body: string}>  $generated
     */
    protected function dedupeAcrossBlocks(SeoAudit $audit, $outline, array $ctx, array $generated): void
    {
        if (count($generated) < 2) {
            return;
        }

        // Index: genormaliseerde zin => [suggestion_id, suggestion_id, ...]
        $sentenceOwners = [];
        foreach ($generated as $id => $data) {
            foreach ($this->extractSentences($data['body']) as $sentence) {
                $key = $this->normalizeSentence($sentence);
                if (mb_strlen($key) < 50) {
                    // Te korte fragmenten zijn vaak generiek (bv. "Bij ons
                    // kun je terecht"). Negeer.
                    continue;
                }
                $sentenceOwners[$key]['raw'] ??= $sentence;
                $sentenceOwners[$key]['ids'][] = $id;
            }
        }

        // Duplicaten verzamelen per blok.
        $avoidByBlock = [];
        foreach ($sentenceOwners as $entry) {
            $owners = array_unique($entry['ids']);
            if (count($owners) < 2) {
                continue;
            }
            foreach ($owners as $id) {
                $avoidByBlock[$id][] = $entry['raw'];
            }
        }

        if ($avoidByBlock === []) {
            return;
        }

        // Bepaal welk blok elke duplicaat-zin mag houden (eerste in
        // outline-volgorde) en welke moeten regenereren met die zin als
        // avoid-instructie.
        $blockOrder = array_keys($generated);
        $orderIndex = array_flip($blockOrder);

        // Voor elke gedupliceerde zin: kies de "winner" (eerste blok in
        // outline-volgorde). De overige blokken krijgen de zin als avoid.
        $regenerateAvoids = [];
        foreach ($sentenceOwners as $entry) {
            $owners = array_values(array_unique($entry['ids']));
            if (count($owners) < 2) {
                continue;
            }
            usort($owners, fn ($a, $b) => ($orderIndex[$a] ?? 0) <=> ($orderIndex[$b] ?? 0));
            $winner = array_shift($owners);
            foreach ($owners as $loserId) {
                $regenerateAvoids[$loserId][] = $entry['raw'];
            }
            // Winner zelf hergenereert niet, dus expliciet leegmaken.
            $regenerateAvoids[$winner] = $regenerateAvoids[$winner] ?? [];
            if (isset($avoidByBlock[$winner])) {
                // Houd alleen avoids over die NIET in deze winnaar zelf zaten.
            }
        }

        foreach ($regenerateAvoids as $suggestionId => $avoidList) {
            $avoidList = array_values(array_unique($avoidList));
            if ($avoidList === []) {
                continue;
            }

            $data = $generated[$suggestionId] ?? null;
            if (! $data) {
                continue;
            }

            $newBody = $this->generateBody(
                $ctx,
                $data['heading'],
                $data['level'],
                (string) ($outline->h1 ?? ''),
                (string) ($outline->summary ?? ''),
                $avoidList,
            );

            if ($newBody === '') {
                // Regeneratie faalde - laat originele body staan, alleen
                // de duplicate-zinnen eruit strippen als hard fallback.
                $newBody = $this->stripPhrases($data['body'], $avoidList);
                if ($newBody === '') {
                    continue;
                }
            }

            $tag = 'h'.$data['level'];
            $html = "<{$tag}>".e($data['heading'])."</{$tag}>".$newBody;

            $audit->blockSuggestions()->whereKey($suggestionId)->update([
                'suggested_value' => $html,
                'reason' => 'Op basis van outline heading: '.$data['heading'].' (hergegenereerd om duplicate content te vermijden)',
            ]);
        }
    }

    /**
     * Snijdt HTML body op in zinnen door tags te strippen en op punt/!/?
     * te splitsen. Geeft alleen zinnen ≥ 40 chars terug.
     *
     * @return array<int, string>
     */
    protected function extractSentences(string $html): array
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        if ($plain === '') {
            return [];
        }

        $parts = preg_split('/(?<=[\.\!\?])\s+(?=[A-Z0-9])/u', $plain) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => mb_strlen($s) >= 40));
    }

    protected function normalizeSentence(string $sentence): string
    {
        $s = mb_strtolower($sentence);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s) ?? '';
        $s = preg_replace('/\s+/u', ' ', $s) ?? '';

        return trim($s);
    }

    /**
     * @param  array<int, string>  $phrases
     */
    protected function stripPhrases(string $html, array $phrases): string
    {
        $clean = $html;
        foreach ($phrases as $phrase) {
            $needle = preg_quote($phrase, '/');
            $clean = preg_replace('/' . $needle . '/iu', '', $clean) ?? $clean;
        }

        return trim($clean);
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
            $seededKeywords = Keyword::query()
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
