<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Services\LinkCandidatesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSectionBodyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $draftId,
        public string $sectionId,
    ) {}

    public function handle(LinkCandidatesService $links): void
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
            Log::warning('GenerateSectionBodyJob: section id not found', ['draft_id' => $draft->id, 'section_id' => $this->sectionId]);

            return;
        }

        $candidates = $links->forLocale($draft->locale ?? 'nl', 20);

        try {
            $response = Ai::json($this->buildPrompt($draft, $sections, $index, $candidates));
        } catch (\Throwable $e) {
            Log::error('GenerateSectionBodyJob: AI call threw', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);
            $sections[$index]['error_message'] = 'AI-aanroep gefaald: '.$e->getMessage();
            $draft->update(['h2_sections' => $sections]);
            $this->maybeUpdateStatus($draft);

            return;
        }

        if ($response === null) {
            Log::warning('GenerateSectionBodyJob: Ai::json returned null', ['draft_id' => $draft->id]);
            $sections[$index]['error_message'] = 'AI-provider gaf geen JSON terug. Controleer of er een Json-provider geconfigureerd is in dashed-ai.';
            $draft->update(['h2_sections' => $sections]);
            $this->maybeUpdateStatus($draft);

            return;
        }

        $body = $this->extractBody($response);

        if ($body === '') {
            Log::warning('GenerateSectionBodyJob: empty body in AI response', [
                'draft_id' => $draft->id,
                'response_keys' => array_keys($response),
            ]);
            $sections[$index]['error_message'] = 'AI gaf geen bruikbare body terug. Ruwe keys: '.implode(', ', array_keys($response));
            $draft->update(['h2_sections' => $sections]);
            $this->maybeUpdateStatus($draft);

            return;
        }

        $sections[$index]['body'] = $body;
        unset($sections[$index]['error_message']);
        $draft->update(['h2_sections' => $sections]);
        $this->maybeUpdateStatus($draft);
    }

    private function extractBody(array $response): string
    {
        foreach (['body', 'html', 'content', 'text'] as $key) {
            if (! empty($response[$key]) && is_string($response[$key])) {
                return trim($response[$key]);
            }
        }

        return '';
    }

    private function maybeUpdateStatus(ContentDraft $draft): void
    {
        $draft->refresh();
        $sections = (array) ($draft->h2_sections ?? []);

        if (empty($sections)) {
            return;
        }

        $withBody = 0;
        foreach ($sections as $section) {
            if (! empty($section['body'] ?? null)) {
                $withBody++;
            }
        }

        if ($withBody === count($sections) && $draft->status !== 'ready') {
            $draft->update(['status' => 'ready']);
        }
    }

    protected function buildPrompt(ContentDraft $draft, array $sections, int $index, array $candidates): string
    {
        $keywords = $draft->keywords->map(function ($k) {
            $volume = $k->volume_exact ? "volume {$k->volume_exact}" : "volume {$k->volume_indication}";

            return "- {$k->keyword} ({$k->type}, {$volume})";
        })->implode("\n");

        $outline = collect($sections)->map(fn ($s, $i) => ($i === $index ? '>> ' : '   ').($s['heading'] ?? ''))->implode("\n");
        $current = $sections[$index];

        $candidatesJson = json_encode(array_map(fn ($c) => ['title' => $c['title'], 'url' => $c['url']], $candidates), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<TXT
Schrijf HTML body voor de gemarkeerde (>>) sectie van artikel "{$draft->name}" (taal: {$draft->locale}).

Outline (>> is de te schrijven sectie):
{$outline}

Huidige sectie:
- heading: {$current['heading']}
- intent: {$current['intent']}

Keywords om thematisch te gebruiken (primaire keywords zwaarder):
{$keywords}

Interne link-kandidaten (gebruik 1 tot 3 die thematisch passen):
{$candidatesJson}

Regels (hard):
- Retourneer HTML-string in veld "body".
- Alleen deze tags: p, ul, ol, li, strong, em, a.
- Geen img, geen script, geen style, geen heading tags (h1-h6).
- 1 tot 3 interne links als <a href="...">anchor</a> naar URLs uit de link-kandidaten. Gebruik alleen URLs die exact in de lijst staan.
- Geen em-dashes. Actieve vorm. "je"-vorm.
- Geen AI-clichés ("duik in", "ontdek de geheimen").
- Laat andere secties ongemoeid, schrijf alleen deze sectie.

Retourneer JSON: {"body": "<p>...</p>"}
TXT;
    }
}
