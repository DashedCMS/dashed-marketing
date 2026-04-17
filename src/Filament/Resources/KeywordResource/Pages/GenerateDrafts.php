<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages;

use Filament\Resources\Pages\Page;
use Filament\Notifications\Notification;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Services\ContentMatcher;
use Dashed\DashedMarketing\Jobs\GenerateContentDraftJob;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource;

class GenerateDrafts extends Page
{
    protected static string $resource = KeywordResource::class;

    protected string $view = 'dashed-marketing::filament.pages.generate-drafts';

    public array $preview = [];

    public array $overrides = [];

    public string $locale = 'nl';

    public function mount(): void
    {
        $this->locale = request('locale', config('app.locale', 'nl'));
        $this->buildPreview();
    }

    public function buildPreview(): void
    {
        $matcher = app(ContentMatcher::class);
        $this->preview = Keyword::query()
            ->where('locale', $this->locale)
            ->whereHas('contentClusters')
            ->where('status', '!=', 'rejected')
            ->with('contentClusters')
            ->get()
            ->map(function ($keyword) use ($matcher) {
                $match = $matcher->match($keyword);
                $cluster = $keyword->contentClusters->first();

                return [
                    'id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'cluster' => $cluster?->name,
                    'content_type' => $cluster?->content_type,
                    'action' => $match !== null ? 'improve' : 'create',
                    'match_title' => $match !== null
                        ? ($match['subject_type']::find($match['subject_id'])?->name ?? 'unknown')
                        : null,
                    'match_score' => $match['score'] ?? null,
                    'match_strategy' => $match['strategy'] ?? null,
                ];
            })
            ->toArray();
    }

    public function confirmGeneration(): void
    {
        foreach ($this->preview as $row) {
            $override = $this->overrides[$row['id']] ?? [];
            if (($override['skip'] ?? false) === true) {
                continue;
            }
            GenerateContentDraftJob::dispatch($row['id'], $override);
        }

        Notification::make()->title('Generatie gestart voor '.count($this->preview).' keywords')->success()->send();
        $this->redirect(KeywordResource::getUrl('index'));
    }
}
