<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Jobs\GenerateClusterConceptsJob;
use Dashed\DashedMarketing\Jobs\GenerateDraftFaqsJob;
use Dashed\DashedMarketing\Jobs\GenerateDraftMetaJob;
use Dashed\DashedMarketing\Jobs\GenerateSectionBodyJob;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Models\ContentDraftSection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

class EditContentCluster extends EditRecord
{
    protected static string $resource = ContentClusterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_concepts')
                ->label('Genereer concepten')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->schema([
                    TextInput::make('count')
                        ->label('Aantal concepten')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(4)
                        ->required(),
                    Textarea::make('briefing')
                        ->label('Briefing (optioneel)')
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->requiresConfirmation(fn () => ! empty($this->record->pending_concepts))
                ->modalDescription(fn () => ! empty($this->record->pending_concepts)
                    ? 'Er staan al concepten. Deze worden overschreven door de nieuwe generatie.'
                    : null)
                ->action(function (array $data) {
                    GenerateClusterConceptsJob::dispatch(
                        $this->record->id,
                        (int) $data['count'],
                        $data['briefing'] ?? null,
                    );
                    Notification::make()
                        ->title('Concepten worden gegenereerd')
                        ->body('Ververs de pagina om resultaten te zien')
                        ->success()
                        ->send();
                }),
            Action::make('make_drafts')
                ->label('Maak drafts van concepten')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->disabled(fn () => empty($this->record->pending_concepts))
                ->action(function () {
                    $cluster = $this->record->fresh();
                    $concepts = $cluster->pending_concepts ?? [];
                    $created = 0;
                    $routeModels = (array) cms()->builder('routeModels');

                    foreach ($concepts as $concept) {
                        if (empty($concept['title'] ?? null)) {
                            continue;
                        }

                        $typeKey = (string) ($concept['suggested_target_type'] ?? '');
                        $entry = $routeModels[$typeKey] ?? null;
                        $targetClass = is_array($entry) ? ($entry['class'] ?? null) : null;

                        $draft = ContentDraft::create([
                            'content_cluster_id' => $cluster->id,
                            'name' => $concept['title'],
                            'slug' => Str::slug($concept['title']),
                            'keyword' => $concept['title'],
                            'locale' => $cluster->locale ?? 'nl',
                            'status' => 'writing',
                            'subject_type' => $targetClass,
                            'subject_id' => $concept['target_id'] ?? null,
                            'instruction' => $concept['description'] ?? null,
                        ]);

                        if (! empty($concept['keyword_ids'])) {
                            $draft->keywords()->attach($concept['keyword_ids']);
                        }

                        $createdSections = [];
                        foreach ((array) ($concept['h2_sections'] ?? []) as $index => $section) {
                            $createdSections[] = ContentDraftSection::create([
                                'content_draft_id' => $draft->id,
                                'sort_order' => $index,
                                'heading' => (string) ($section['heading'] ?? ''),
                                'intent' => (string) ($section['intent'] ?? ''),
                            ]);
                        }

                        $sectionJobs = array_map(
                            fn (ContentDraftSection $section) => new GenerateSectionBodyJob($section->id),
                            $createdSections,
                        );
                        $sectionJobs[] = new GenerateDraftFaqsJob($draft->id);
                        $sectionJobs[] = new GenerateDraftMetaJob($draft->id, overwrite: true);
                        Bus::chain($sectionJobs)->dispatch();

                        $created++;
                    }

                    $cluster->update(['pending_concepts' => null]);

                    Notification::make()
                        ->title("{$created} drafts aangemaakt - content wordt op de achtergrond gegenereerd")
                        ->success()
                        ->send();

                    return redirect(ContentDraftResource::getUrl('index', [
                        'tableFilters' => [
                            'content_cluster_id' => ['value' => $cluster->id],
                        ],
                    ]));
                }),
            Action::make('view_drafts')
                ->label('Bekijk concepten')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(fn () => ContentDraftResource::getUrl('index', [
                    'tableFilters' => [
                        'content_cluster_id' => ['value' => $this->record->id],
                    ],
                ])),
            DeleteAction::make(),
        ];
    }
}
