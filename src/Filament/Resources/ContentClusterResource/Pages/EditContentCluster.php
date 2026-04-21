<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource;
use Dashed\DashedMarketing\Jobs\GenerateClusterConceptsJob;
use Dashed\DashedMarketing\Models\ContentDraft;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
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
                    Select::make('count')
                        ->label('Aantal concepten')
                        ->options([5 => '5', 10 => '10', 15 => '15', 20 => '20'])
                        ->default(10)
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
                        ->body('De pagina ververst automatisch wanneer klaar.')
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

                        $h2 = [];
                        $order = 0;
                        foreach ((array) ($concept['h2_sections'] ?? []) as $section) {
                            $h2[] = [
                                'id' => (string) ($section['id'] ?? Str::uuid()),
                                'heading' => (string) ($section['heading'] ?? ''),
                                'intent' => (string) ($section['intent'] ?? ''),
                                'body' => '',
                                'order' => $order++,
                            ];
                        }

                        $draft = ContentDraft::create([
                            'content_cluster_id' => $cluster->id,
                            'name' => $concept['title'],
                            'slug' => Str::slug($concept['title']),
                            'keyword' => $concept['title'],
                            'locale' => $cluster->locale ?? 'nl',
                            'status' => 'concept',
                            'subject_type' => $targetClass,
                            'subject_id' => $concept['target_id'] ?? null,
                            'instruction' => $concept['description'] ?? null,
                            'h2_sections' => $h2,
                        ]);

                        if (! empty($concept['keyword_ids'])) {
                            $draft->keywords()->attach($concept['keyword_ids']);
                        }

                        $created++;
                    }

                    $cluster->update(['pending_concepts' => null]);

                    Notification::make()
                        ->title("{$created} drafts aangemaakt")
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
