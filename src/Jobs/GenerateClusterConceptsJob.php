<?php

namespace Dashed\DashedMarketing\Jobs;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    ) {}

    public function handle(): void
    {
        $cluster = ContentCluster::with('keywords')->find($this->clusterId);
        if ($cluster === null) {
            return;
        }

        $allowedKeywordIds = $cluster->keywords->pluck('id')->all();

        $response = Ai::json($this->buildPrompt($cluster)) ?? [];
        $concepts = $response['concepts'] ?? [];

        if (empty($concepts)) {
            Log::warning('GenerateClusterConceptsJob: AI returned no concepts', ['cluster_id' => $cluster->id]);

            return;
        }

        $routeModels = (array) cms()->builder('routeModels');
        $normalized = [];

        foreach ($concepts as $c) {
            if (empty($c['title'] ?? null)) {
                continue;
            }

            $keywordIds = array_values(array_intersect(
                $allowedKeywordIds,
                array_map('intval', (array) ($c['keyword_ids'] ?? []))
            ));
            if (empty($keywordIds)) {
                Log::warning('GenerateClusterConceptsJob: concept skipped (no valid keywords)', ['title' => $c['title']]);

                continue;
            }

            $h2 = [];
            foreach ((array) ($c['h2_sections'] ?? []) as $section) {
                if (empty($section['heading'] ?? null)) {
                    continue;
                }
                $h2[] = [
                    'id' => (string) Str::uuid(),
                    'heading' => (string) $section['heading'],
                    'intent' => (string) ($section['intent'] ?? ''),
                ];
            }
            if (empty($h2)) {
                Log::warning('GenerateClusterConceptsJob: concept skipped (no h2 sections)', ['title' => $c['title']]);

                continue;
            }

            $targetTypeKey = (string) ($c['suggested_target_type'] ?? '');
            $entry = $routeModels[$targetTypeKey] ?? null;
            $targetClass = is_array($entry) ? ($entry['class'] ?? null) : null;

            $targetMode = ($c['target_mode'] ?? 'new') === 'overwrite' ? 'overwrite' : 'new';
            $targetId = null;
            $targetPreviewName = null;

            if ($targetMode === 'overwrite' && $targetClass && class_exists($targetClass) && ! empty($c['target_id'])) {
                try {
                    $existing = $targetClass::find($c['target_id']);
                } catch (\Throwable) {
                    $existing = null;
                }
                if ($existing !== null) {
                    $targetId = $existing->getKey();
                    $nameField = is_array($entry) ? ($entry['nameField'] ?? 'name') : 'name';
                    $preview = $existing->{$nameField} ?? '';
                    if (is_array($preview)) {
                        $preview = $preview[$cluster->locale] ?? reset($preview) ?? '';
                    }
                    $targetPreviewName = (string) $preview;
                } else {
                    $targetMode = 'new';
                }
            } elseif ($targetMode === 'overwrite') {
                $targetMode = 'new';
            }

            $normalized[] = [
                'title' => (string) $c['title'],
                'description' => (string) ($c['description'] ?? ''),
                'suggested_target_type' => $targetTypeKey,
                'target_mode' => $targetMode,
                'target_id' => $targetId,
                'target_preview_name' => $targetPreviewName,
                'keyword_ids' => $keywordIds,
                'h2_sections' => $h2,
            ];
        }

        if (empty($normalized)) {
            return;
        }

        $cluster->update(['pending_concepts' => $normalized]);
    }

    protected function buildPrompt(ContentCluster $cluster): string
    {
        $approvedKeywords = $cluster->keywords
            ->where('status', 'approved')
            ->map(function (Keyword $k) {
                $volume = $k->volume_exact ? "{$k->volume_exact}/mnd" : ($k->volume_indication ?? 'onbekend volume');

                return "- id={$k->id}, keyword={$k->keyword}, type={$k->type}, volume={$volume}, intent={$k->search_intent}";
            })
            ->implode("\n");

        $routeModels = (array) cms()->builder('routeModels');
        $existingBlocks = [];
        foreach ($routeModels as $key => $entry) {
            $class = is_array($entry) ? ($entry['class'] ?? null) : null;
            if (! $class || ! class_exists($class)) {
                continue;
            }
            try {
                $records = $class::query()->limit(50)->get();
            } catch (\Throwable) {
                continue;
            }
            $rows = [];
            foreach ($records as $record) {
                $name = $record->name ?? $record->title ?? '';
                if (is_array($name)) {
                    $name = $name[$cluster->locale] ?? reset($name) ?? '';
                }
                $slug = $record->slug ?? '';
                if (is_array($slug)) {
                    $slug = $slug[$cluster->locale] ?? reset($slug) ?? '';
                }
                $rows[] = "  - id={$record->getKey()}, name={$name}, slug={$slug}";
            }
            $existingBlocks[] = "Type \"{$key}\":\n".($rows ? implode("\n", $rows) : '  (geen bestaande records)');
        }
        $existingBlocksStr = implode("\n\n", $existingBlocks);

        $briefing = $this->briefing ? "Briefing: {$this->briefing}\n" : '';

        return <<<TXT
Genereer {$this->count} content concepten voor cluster "{$cluster->name}" (locale: {$cluster->locale}).

Cluster:
- theme: {$cluster->theme}
- type: {$cluster->content_type}
- description: {$cluster->description}

Beschikbare cluster keywords:
{$approvedKeywords}

Bestaande records per type:
{$existingBlocksStr}

{$briefing}

Per concept retourneer:
- title: H1 titel, max 80 tekens
- description: 1 zin samenvatting
- suggested_target_type: een van de type-sleutels hierboven
- target_mode: "new" of "overwrite". Gebruik "overwrite" als een bestaand record thematisch sterk overlapt.
- target_id: null voor nieuw, of het id van het bestaande record bij overwrite.
- keyword_ids: array met keyword ids uit bovenstaande lijst (alleen ids die passen bij dit concept, primaire keyword eerst). Gebruik volume als leidraad: hogere volumes zwaarder.
- h2_sections: minimaal 3 items, elk met heading (string) en intent (string, waar gaat deze sectie over, max 200 tekens).

Regels:
- Geen em-dashes. Actieve vorm. "je"-vorm.
- Geen AI-clichés ("duik in", "ontdek de geheimen").
- Elk concept uniek en concreet.

Retourneer ONLY valid JSON: {"concepts": [{...}]}
TXT;
    }
}
