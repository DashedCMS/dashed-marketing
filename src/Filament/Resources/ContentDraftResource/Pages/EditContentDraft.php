<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Jobs\RegenerateContentSectionJob;
use Dashed\DashedMarketing\Models\ContentDraft;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;

class EditContentDraft extends Page
{
    protected static string $resource = ContentDraftResource::class;

    protected string $view = 'dashed-marketing::filament.pages.edit-content-draft';

    public ContentDraft $record;

    public array $sections = [];

    public array $linkCandidates = [];

    public function mount(ContentDraft $record): void
    {
        $this->record = $record;
        $this->sections = $record->h2_sections ?? [];
        $this->linkCandidates = $this->buildLinkCandidates();
    }

    public function regenerateSection(string $sectionId): void
    {
        RegenerateContentSectionJob::dispatchSync($this->record->id, $sectionId);
        $this->record->refresh();
        $this->sections = $this->record->h2_sections ?? [];
        Notification::make()->title('Sectie hergegeneerd')->success()->send();
    }

    public function autosave(): void
    {
        $this->record->update(['h2_sections' => $this->sections]);
    }

    public function addSection(): void
    {
        $this->sections[] = [
            'id' => (string) Str::uuid(),
            'heading' => 'Nieuwe sectie',
            'body' => '',
            'order' => count($this->sections),
        ];
        $this->autosave();
    }

    public function removeSection(string $sectionId): void
    {
        $this->sections = array_values(array_filter($this->sections, fn ($s) => $s['id'] !== $sectionId));
        $this->autosave();
    }

    public function moveSection(string $sectionId, int $direction): void
    {
        $index = collect($this->sections)->search(fn ($s) => $s['id'] === $sectionId);
        if ($index === false) {
            return;
        }
        $swapWith = $index + $direction;
        if ($swapWith < 0 || $swapWith >= count($this->sections)) {
            return;
        }
        [$this->sections[$index], $this->sections[$swapWith]] = [$this->sections[$swapWith], $this->sections[$index]];
        $this->autosave();
    }

    protected function buildLinkCandidates(): array
    {
        $pool = [];
        try {
            $routeModels = (array) cms()->builder('routeModels');
        } catch (\Throwable) {
            return [];
        }

        foreach ($routeModels as $entry) {
            $class = is_array($entry) ? ($entry['class'] ?? null) : (is_string($entry) ? $entry : null);
            if (! $class || ! class_exists($class)) {
                continue;
            }
            foreach ($class::query()->limit(20)->get() as $entity) {
                $pool[] = [
                    'type' => class_basename($class),
                    'title' => $entity->name ?? $entity->title ?? '',
                    'url' => method_exists($entity, 'getUrl') ? $entity->getUrl() : '/'.($entity->slug ?? ''),
                ];
            }
        }

        return array_slice($pool, 0, config('dashed-marketing-content.internal_links.candidate_pool_size', 20));
    }
}
