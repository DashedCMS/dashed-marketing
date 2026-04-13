<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\KeywordResearch;

class ClusterKeywordsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $workspaceId,
        public string $mode = 'full',
    ) {
    }

    public function handle(): void
    {
        $workspace = KeywordResearch::findOrFail($this->workspaceId);
        $workspace->update(['status' => 'analyzing', 'progress_message' => 'Clustering…']);

        $keywords = Keyword::query()
            ->where('keyword_research_id', $workspace->id)
            ->where('status', '!=', 'rejected')
            ->get();

        if ($keywords->isEmpty()) {
            $workspace->update(['status' => 'ready', 'progress_message' => 'Geen keywords om te clusteren.']);

            return;
        }

        if ($this->mode === 'full') {
            $workspace->contentClusters()->each(function (ContentCluster $cluster) {
                $cluster->keywords()->detach();
            });
            $workspace->contentClusters()->delete();
        }

        $existingClusters = $this->mode === 'incremental'
            ? $workspace->contentClusters()->with('keywords')->get()
            : collect();

        $response = Ai::json($this->buildPrompt($keywords, $existingClusters, $workspace->locale ?? 'nl')) ?? [];
        $clusters = $response['clusters'] ?? [];

        $validKeywordsLower = $keywords->pluck('keyword')->map(fn ($k) => mb_strtolower((string) $k))->all();

        foreach ($clusters as $clusterData) {
            $keywordList = array_values(array_filter(
                (array) ($clusterData['keywords'] ?? []),
                fn ($kw) => in_array(mb_strtolower((string) $kw), $validKeywordsLower, true),
            ));

            if (empty($keywordList)) {
                Log::warning('Cluster skipped (no valid keywords)', ['cluster' => $clusterData]);

                continue;
            }

            $cluster = $workspace->contentClusters()->create([
                'name' => $clusterData['name'] ?? 'Cluster',
                'theme' => $clusterData['theme'] ?? null,
                'content_type' => $clusterData['content_type'] ?? 'blog',
                'description' => $clusterData['description'] ?? null,
                'status' => 'planned',
            ]);

            $keywordListLower = array_map(fn ($x) => mb_strtolower((string) $x), $keywordList);
            $keywordIds = $keywords
                ->filter(fn (Keyword $k) => in_array(mb_strtolower((string) $k->keyword), $keywordListLower, true))
                ->pluck('id');

            $cluster->keywords()->sync($keywordIds);
        }

        $workspace->update(['status' => 'ready', 'progress_message' => 'Clustering klaar.']);
    }

    protected function buildPrompt($keywords, $existingClusters, string $locale): string
    {
        $keywordList = $keywords->pluck('keyword')->map(fn ($k) => "- {$k}")->implode("\n");
        $existing = $existingClusters->map(function (ContentCluster $cluster) {
            return "- {$cluster->name} ({$cluster->content_type}): ".$cluster->keywords->pluck('keyword')->implode(', ');
        })->implode("\n");

        $existingSection = $existingClusters->isEmpty()
            ? ''
            : "\n\nBestaande clusters:\n{$existing}\n\nPlaats nieuwe keywords in bestaande clusters waar logisch, maak alleen nieuwe clusters voor echte outliers.";

        return <<<TXT
Cluster de onderstaande keywords voor een Nederlandstalige website (taal: {$locale}).

Regels:
- Gebruik UITSLUITEND de onderstaande keywords. Verzin er geen bij.
- Elke keyword in maximaal één cluster.
- Per cluster: name (kort), theme (onderwerp), content_type (blog|landing_page|category|product|faq), description (1-2 zinnen), keywords (array uit de input).
- Retourneer ONLY valid JSON: {"clusters": [{...}, ...]}

Keywords:
{$keywordList}
{$existingSection}
TXT;
    }
}
