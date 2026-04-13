<?php

namespace Dashed\DashedMarketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedMarketing\Models\Keyword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedMarketing\Models\KeywordImport;
use Dashed\DashedMarketing\Models\KeywordResearch;

class ImportKeywordsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public int $workspaceId,
        public int $importId,
        public array $rows,
        public string $duplicateStrategy = 'skip',
    ) {
    }

    public function handle(): void
    {
        $workspace = KeywordResearch::findOrFail($this->workspaceId);
        $import = KeywordImport::findOrFail($this->importId);

        $written = 0;
        DB::transaction(function () use ($workspace, &$written) {
            foreach ($this->rows as $row) {
                $keyword = trim((string) ($row['keyword'] ?? ''));
                if ($keyword === '') {
                    continue;
                }

                $existing = Keyword::query()
                    ->where('keyword_research_id', $workspace->id)
                    ->where('keyword', $keyword)
                    ->first();

                if ($existing !== null && $this->duplicateStrategy === 'skip') {
                    continue;
                }

                $attributes = [
                    'keyword_research_id' => $workspace->id,
                    'keyword' => $keyword,
                    'type' => $row['type'] ?? 'secondary',
                    'search_intent' => $row['search_intent'] ?? 'informational',
                    'difficulty' => $row['difficulty'] ?? 'medium',
                    'volume_indication' => $row['volume_indication'] ?? 'medium',
                    'volume_exact' => isset($row['volume_exact']) ? (int) $row['volume_exact'] : null,
                    'cpc' => isset($row['cpc']) ? (float) $row['cpc'] : null,
                    'source' => 'csv',
                    'status' => 'new',
                    'notes' => $row['notes'] ?? null,
                ];

                if ($existing !== null) {
                    $existing->update($attributes);
                } else {
                    Keyword::create($attributes);
                }

                $written++;
            }
        });

        $import->update(['row_count' => $written]);
        $workspace->update([
            'status' => 'ready',
            'progress_message' => "Imported {$written} keywords.",
        ]);
    }
}
