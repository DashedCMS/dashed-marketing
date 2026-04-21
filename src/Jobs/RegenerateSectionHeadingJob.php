<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Support\Facades\Log;

class RegenerateSectionHeadingJob
{
    public function __construct(
        public int $draftId,
        public string $sectionId,
    ) {}

    public function handle(): void
    {
        $draft = ContentDraft::with('contentCluster', 'keywords')->find($this->draftId);
        if ($draft === null) {
            return;
        }

        $sections = $draft->h2_sections ?? [];
        $index = null;
        foreach ($sections as $i => $section) {
            if (($section['id'] ?? null) === $this->sectionId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            Log::warning('RegenerateSectionHeadingJob: section id not found', ['draft_id' => $draft->id, 'section_id' => $this->sectionId]);

            return;
        }

        $response = Ai::json($this->buildPrompt($draft, $sections, $index)) ?? [];
        $heading = (string) ($response['heading'] ?? '');
        $intent = (string) ($response['intent'] ?? '');
        if ($heading === '') {
            Log::warning('RegenerateSectionHeadingJob: empty AI response', ['draft_id' => $draft->id]);

            return;
        }

        $sections[$index]['heading'] = $heading;
        $sections[$index]['intent'] = $intent;
        $draft->update(['h2_sections' => $sections]);
    }

    protected function buildPrompt(ContentDraft $draft, array $sections, int $index): string
    {
        $keywords = $draft->keywords->map(fn ($k) => "- {$k->keyword}")->implode("\n");
        $outline = collect($sections)->map(fn ($s, $i) => ($i === $index ? '>> ' : '   ').($s['heading'] ?? ''))->implode("\n");
        $current = $sections[$index];

        return <<<TXT
Regenereer alleen de heading en intent van de gemarkeerde (>>) sectie voor artikel "{$draft->name}" (taal: {$draft->locale}).

Huidige outline (>> is de te herschrijven sectie):
{$outline}

Huidige heading: {$current['heading']}
Huidige intent: {$current['intent']}

Gekoppelde keywords:
{$keywords}

Regels:
- Geen em-dashes. Actieve vorm. "je"-vorm.
- Heading max 70 tekens, concreet.
- Intent max 200 tekens, beschrijft waar deze sectie over gaat.
- Laat de andere secties ongemoeid.

Retourneer JSON: {"heading": "...", "intent": "..."}
TXT;
    }
}
