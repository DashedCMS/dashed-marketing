<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Dashed\DashedMarketing\Jobs\GenerateContentDraftJob;
use Dashed\DashedMarketing\Models\KeywordResearch;
use Dashed\DashedMarketing\Services\ContentMatcher;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class GenerateDrafts extends Page
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected string $view = 'dashed-marketing::filament.pages.generate-drafts';

    public KeywordResearch $record;

    /** @var array<int, array<string, mixed>> */
    public array $preview = [];

    /** @var array<int, array<string, mixed>> */
    public array $overrides = [];

    public function mount(KeywordResearch $record): void
    {
        $this->record = $record;
        $this->buildPreview();
    }

    public function buildPreview(): void
    {
        $matcher = app(ContentMatcher::class);

        $this->preview = $this->record->keywords()
            ->whereHas('contentClusters')
            ->where('status', '!=', 'rejected')
            ->with('contentClusters')
            ->get()
            ->map(function ($keyword) use ($matcher) {
                $match = $matcher->match($keyword);
                $cluster = $keyword->contentClusters->first();

                $matchTitle = null;
                if ($match !== null && class_exists($match['subject_type'])) {
                    $entity = ($match['subject_type'])::find($match['subject_id']);
                    $matchTitle = $entity?->name ?? $entity?->title ?? 'unknown';
                }

                return [
                    'id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'cluster' => $cluster?->name,
                    'content_type' => $cluster?->content_type,
                    'action' => $match !== null ? 'improve' : 'create',
                    'match_title' => $matchTitle,
                    'match_score' => $match['score'] ?? null,
                    'match_strategy' => $match['strategy'] ?? null,
                ];
            })
            ->toArray();
    }

    public function confirmGeneration(): void
    {
        $dispatched = 0;
        foreach ($this->preview as $row) {
            $override = $this->overrides[$row['id']] ?? [];
            if (($override['skip'] ?? false) === true) {
                continue;
            }
            GenerateContentDraftJob::dispatch($row['id'], $override);
            $dispatched++;
        }

        Notification::make()
            ->title("Generatie gestart voor {$dispatched} keywords")
            ->success()
            ->send();

        $this->redirect(KeywordWorkspaceResource::getUrl('edit', ['record' => $this->record]));
    }
}
