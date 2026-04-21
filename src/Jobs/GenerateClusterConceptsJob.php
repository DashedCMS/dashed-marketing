<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentCluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateClusterConceptsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $clusterId,
        public int $count = 10,
        public ?string $briefing = null,
    ) {
    }

    public function handle(): void
    {
        $cluster = ContentCluster::with('keywords')->find($this->clusterId);
        if ($cluster === null) {
            return;
        }

        $response = Ai::json($this->buildPrompt($cluster)) ?? [];
        $concepts = $response['concepts'] ?? [];

        if (empty($concepts)) {
            Log::warning('GenerateClusterConceptsJob: AI returned no concepts', ['cluster_id' => $cluster->id]);

            return;
        }

        $normalized = [];
        foreach ($concepts as $c) {
            if (! isset($c['title'])) {
                continue;
            }
            $normalized[] = [
                'title' => (string) $c['title'],
                'description' => (string) ($c['description'] ?? ''),
                'suggested_target_type' => (string) ($c['suggested_target_type'] ?? ''),
            ];
        }

        $cluster->update(['pending_concepts' => $normalized]);
    }

    protected function buildPrompt(ContentCluster $cluster): string
    {
        $approvedKeywords = $cluster->keywords
            ->filter(fn ($k) => $k->status === 'approved')
            ->pluck('keyword')
            ->implode(', ');

        $routeTypes = [];
        try {
            foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                $routeTypes[] = (string) $key;
            }
        } catch (\Throwable) {
            //
        }
        $routeTypesStr = implode(', ', $routeTypes);

        $briefing = $this->briefing ? "Briefing van gebruiker: {$this->briefing}\n" : '';

        return <<<TXT
Genereer {$this->count} content concepten voor cluster "{$cluster->name}" (taal: {$cluster->locale}).

Cluster context:
- Thema: {$cluster->theme}
- Type: {$cluster->content_type}
- Beschrijving: {$cluster->description}
- Gekoppelde zoekwoorden: {$approvedKeywords}

{$briefing}
Per concept, lever:
- title: korte werktitel voor de pagina (max 80 tekens).
- description: 1 zin samenvatting waar het artikel/pagina over gaat.
- suggested_target_type: een van deze types: {$routeTypesStr}. Kies de meest passende.

Regels:
- Geen em-dashes. Actieve vorm. "je"-vorm.
- Geen AI-clichés ("duik in", "ontdek de geheimen").
- Elk concept moet uniek en concreet zijn.

Retourneer ONLY valid JSON: {"concepts": [{"title": "...", "description": "...", "suggested_target_type": "..."}]}
TXT;
    }
}
