<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Jobs\FillContentDraftJob;
use Dashed\DashedMarketing\Jobs\RegenerateContentSectionJob;
use Dashed\DashedMarketing\Models\ContentDraft;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_content')
                ->label('Genereer content')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn () => in_array($this->record->status, ['concept', 'ready'], true))
                ->schema([
                    Textarea::make('briefing')
                        ->label('Briefing (optioneel)')
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->action(function (array $data) {
                    FillContentDraftJob::dispatch($this->record->id, $data['briefing'] ?? null);
                    Notification::make()
                        ->title('Content wordt gegenereerd')
                        ->body('De pagina ververst automatisch zodra klaar.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('publish')
                ->label('Publiceer')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->visible(fn () => $this->record->status === 'ready')
                ->schema([
                    Select::make('target_type')
                        ->label('Target type')
                        ->options(function () {
                            $options = [];
                            try {
                                foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                                    $name = is_array($entry) ? ($entry['name'] ?? $key) : $key;
                                    $options[$key] = $name;
                                }
                            } catch (\Throwable) {
                                //
                            }

                            return $options;
                        })
                        ->required()
                        ->live(),
                    Select::make('target_id')
                        ->label('Bestaand record bijwerken')
                        ->placeholder('Nieuw record aanmaken')
                        ->options(function ($get) {
                            $type = $get('target_type');
                            if (! $type) {
                                return [];
                            }
                            try {
                                $entry = cms()->builder('routeModels')[$type] ?? null;
                                $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                                if (! $class || ! class_exists($class)) {
                                    return [];
                                }

                                return $class::query()->limit(50)->get()->mapWithKeys(function ($m) {
                                    $name = $m->name ?? $m->title ?? 'Record #'.$m->getKey();
                                    if (is_array($name)) {
                                        $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #'.$m->getKey());
                                    }

                                    return [$m->getKey() => $name];
                                })->all();
                            } catch (\Throwable) {
                                return [];
                            }
                        })
                        ->nullable(),
                ])
                ->action(function (array $data) {
                    $typeKey = $data['target_type'];
                    $entry = cms()->builder('routeModels')[$typeKey] ?? null;
                    $class = is_array($entry) ? ($entry['class'] ?? null) : null;

                    if (! $class || ! class_exists($class)) {
                        Notification::make()->title('Onbekend target type')->danger()->send();

                        return;
                    }

                    $draft = $this->record;
                    $locale = $draft->locale ?? 'nl';

                    // All dashed visitableModels have translatable json name/slug/content.
                    $nameValue = [$locale => $draft->name];
                    $slugValue = [$locale => $draft->slug];
                    $contentValue = [$locale => $draft->h2_sections ?? []];

                    if (! empty($data['target_id'])) {
                        $record = $class::findOrFail($data['target_id']);
                        $existingName = is_array($record->name) ? $record->name : [];
                        $existingSlug = is_array($record->slug) ? $record->slug : [];
                        $existingContent = is_array($record->content) ? $record->content : [];
                        $record->name = array_merge($existingName, $nameValue);
                        $record->slug = array_merge($existingSlug, $slugValue);
                        $record->content = array_merge($existingContent, $contentValue);
                        $record->save();
                    } else {
                        $record = new $class;
                        $record->name = $nameValue;
                        $record->slug = $slugValue;
                        $record->content = $contentValue;
                        $record->save();
                    }

                    $draft->update([
                        'subject_type' => $class,
                        'subject_id' => $record->getKey(),
                        'status' => 'applied',
                        'applied_at' => now(),
                        'applied_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Gepubliceerd naar '.class_basename($class))
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reject')
                ->label('Reject draft')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'failed']);
                    $this->redirect(ContentDraftResource::getUrl('index'));
                }),
        ];
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
            $locale = app()->getLocale();
            foreach ($class::query()->limit(20)->get() as $entity) {
                $title = $entity->name ?? $entity->title ?? '';
                if (is_array($title)) {
                    $title = $title[$locale] ?? reset($title) ?? '';
                }
                $slug = $entity->slug ?? '';
                if (is_array($slug)) {
                    $slug = $slug[$locale] ?? reset($slug) ?? '';
                }
                $pool[] = [
                    'type' => class_basename($class),
                    'title' => (string) $title,
                    'url' => method_exists($entity, 'getUrl') ? $entity->getUrl() : '/'.$slug,
                ];
            }
        }

        return array_slice($pool, 0, config('dashed-marketing-content.internal_links.candidate_pool_size', 20));
    }
}
