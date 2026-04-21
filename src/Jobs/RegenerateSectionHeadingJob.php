<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\ContentDraftSection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateSectionHeadingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 45;

    public function __construct(public int $sectionId) {}

    public function handle(): void
    {
        $section = ContentDraftSection::with('contentDraft.keywords')->find($this->sectionId);
        if ($section === null) {
            return;
        }

        $draft = $section->contentDraft;
        if ($draft === null) {
            return;
        }

        $allSections = $draft->sections()->orderBy('sort_order')->get();

        $response = Ai::json($this->buildPrompt($draft, $section, $allSections)) ?? [];
        $heading = trim((string) ($response['heading'] ?? ''));
        $intent = trim((string) ($response['intent'] ?? ''));

        if ($heading === '') {
            Log::warning('RegenerateSectionHeadingJob: empty AI response', [
                'section_id' => $section->id,
            ]);

            return;
        }

        $section->update([
            'heading' => $heading,
            'intent' => $intent ?: $section->intent,
        ]);
    }

    protected function buildPrompt(ContentDraft $draft, ContentDraftSection $current, $allSections): string
    {
        $keywords = $draft->keywords->map(fn ($k) => "- {$k->keyword}")->implode("\n");
        $outline = $allSections
            ->map(fn ($s) => ($s->id === $current->id ? '>> ' : '   ').($s->heading ?? ''))
            ->implode("\n");

        return <<<TXT
Regenereer alleen de heading en intent van de gemarkeerde (>>) sectie voor artikel "{$draft->name}" (taal: {$draft->locale}).

Huidige outline (>> is de te herschrijven sectie):
{$outline}

Huidige heading: {$current->heading}
Huidige intent: {$current->intent}

Gekoppelde keywords:
{$keywords}

Regels:
- Heading max 70 tekens, concreet.
- Intent max 200 tekens, beschrijft waar deze sectie over gaat.
- Laat de andere secties ongemoeid.

Retourneer JSON: {"heading": "...", "intent": "..."}
TXT;
    }
}
