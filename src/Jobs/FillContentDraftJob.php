<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FillContentDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $draftId,
        public ?string $briefing = null,
    ) {
    }

    public function handle(): void
    {
        $draft = ContentDraft::with('contentCluster.keywords')->find($this->draftId);
        if ($draft === null) {
            return;
        }

        $draft->update(['status' => 'writing', 'error_message' => null]);

        try {
            $response = Ai::json($this->buildPrompt($draft)) ?? [];
        } catch (\Throwable $e) {
            Log::error('FillContentDraftJob AI call failed', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);
            $draft->update(['status' => 'concept', 'error_message' => $e->getMessage()]);

            return;
        }

        $sections = $response['h2_sections'] ?? [];
        if (empty($sections)) {
            Log::warning('FillContentDraftJob: empty response', ['draft_id' => $draft->id]);
            $draft->update([
                'status' => 'concept',
                'error_message' => 'AI gaf geen bruikbare content terug.',
            ]);

            return;
        }

        $draft->update([
            'h2_sections' => $sections,
            'article_content' => $response,
            'status' => 'ready',
            'error_message' => null,
        ]);
    }

    protected function buildPrompt(ContentDraft $draft): string
    {
        $cluster = $draft->contentCluster;
        $keywords = $cluster?->keywords->pluck('keyword')->implode(', ') ?? '';
        $briefing = $this->briefing ? "\nExtra briefing: {$this->briefing}\n" : '';

        return <<<TXT
Schrijf een volledig artikel voor de volgende pagina in taal {$draft->locale}.

Titel: {$draft->name}
Slug: {$draft->slug}
Focus keyword: {$draft->keyword}
Cluster: {$cluster?->name}
Gerelateerde keywords: {$keywords}
{$briefing}
Regels (hard):
- Geen em-dashes. Komma's gebruiken.
- Geen AI-clichés ("in dit artikel gaan we", "duik in", "ontdek de geheimen van").
- Actieve vorm, korte zinnen, "je"-vorm.
- Verzin geen feiten.

Lever het artikel als h2_sections array: [{id, heading, body, order}].

Retourneer ONLY valid JSON: {"h2_sections": [...]}
TXT;
    }
}
