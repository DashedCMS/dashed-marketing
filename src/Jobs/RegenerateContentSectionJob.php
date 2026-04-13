<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Services\ArticleSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegenerateContentSectionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $draftId,
        public string $sectionId,
    ) {
    }

    public function handle(ArticleSanitizer $sanitizer): void
    {
        $draft = ContentDraft::findOrFail($this->draftId);
        $sections = $draft->h2_sections ?? [];

        $target = collect($sections)->firstWhere('id', $this->sectionId);
        if ($target === null) {
            return;
        }

        $outline = collect($sections)
            ->map(function ($section) {
                if (($section['id'] ?? null) === $this->sectionId) {
                    return "### TE HERSCHRIJVEN — {$section['heading']}\n{$section['body']}";
                }

                return "### {$section['heading']}\n".mb_substr($section['body'] ?? '', 0, 400);
            })
            ->implode("\n\n");

        $prompt = <<<TXT
Herschrijf ALLEEN de sectie gemarkeerd als "TE HERSCHRIJVEN". Houd dezelfde heading.
Vermijd herhaling met andere secties. Andere secties blijven ongewijzigd in jouw output.

Prompt-hygiëne:
- Geen em-dashes
- Vermijd clichés
- Actieve vorm, korte zinnen, "je"-vorm

Volledige outline:
{$outline}

Retourneer ONLY valid JSON: {"heading": "...", "body": "..."}
TXT;

        $response = Ai::json($prompt);
        if (empty($response['body'] ?? null)) {
            return;
        }

        $draft->pushHistory($sections);

        $updated = array_map(function ($section) use ($response) {
            if (($section['id'] ?? null) === $this->sectionId) {
                $section['heading'] = $response['heading'] ?? $section['heading'];
                $section['body'] = $response['body'];
                $section['regenerated_at'] = now()->toIso8601String();
            }

            return $section;
        }, $sections);

        $updated = $sanitizer->sanitizeSections($updated);
        $draft->update(['h2_sections' => $updated]);
    }
}
