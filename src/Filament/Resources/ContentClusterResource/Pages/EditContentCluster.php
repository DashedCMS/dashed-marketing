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

                    foreach ($concepts as $concept) {
                        if (empty($concept['title'] ?? null)) {
                            continue;
                        }

                        ContentDraft::create([
                            'content_cluster_id' => $cluster->id,
                            'name' => $concept['title'],
                            'slug' => Str::slug($concept['title']),
                            'keyword' => $concept['title'],
                            'locale' => $cluster->locale ?? 'nl',
                            'status' => 'concept',
                            'subject_type' => null,
                            'subject_id' => null,
                            'instruction' => $concept['description'] ?? null,
                        ]);
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
