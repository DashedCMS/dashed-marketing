<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Facades\KeywordData;
use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Dashed\DashedMarketing\Jobs\ClusterKeywordsJob;
use Dashed\DashedMarketing\Managers\KeywordDataManager;
use Dashed\DashedMarketing\Models\Keyword;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditKeywordWorkspace extends EditRecord
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_csv')
                ->label('Importeer CSV/Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn () => KeywordWorkspaceResource::getUrl('import', ['record' => $this->record])),

            Action::make('add_keyword')
                ->label('Voeg keyword toe')
                ->icon('heroicon-o-plus')
                ->schema([
                    TextInput::make('keyword')
                        ->label('Keyword')
                        ->required(),
                    Select::make('search_intent')
                        ->label('Intent')
                        ->options([
                            'informational' => 'Informational',
                            'commercial' => 'Commercial',
                            'transactional' => 'Transactional',
                            'navigational' => 'Navigational',
                        ]),
                    Select::make('difficulty')
                        ->label('Difficulty')
                        ->options([
                            'easy' => 'Easy',
                            'medium' => 'Medium',
                            'hard' => 'Hard',
                        ]),
                    TextInput::make('volume_exact')
                        ->label('Volume')
                        ->numeric(),
                    TextInput::make('cpc')
                        ->label('CPC')
                        ->numeric()
                        ->step(0.01),
                ])
                ->action(function (array $data): void {
                    Keyword::create(array_merge($data, [
                        'keyword_research_id' => $this->record->id,
                        'type' => 'secondary',
                        'status' => 'new',
                        'source' => 'manual',
                    ]));

                    Notification::make()
                        ->title('Keyword toegevoegd')
                        ->success()
                        ->send();
                }),

            Action::make('enrich')
                ->label('Verrijk via API')
                ->icon('heroicon-o-sparkles')
                ->action(function (): void {
                    $manager = app(KeywordDataManager::class);
                    if ($manager->provider()->name() === 'manual') {
                        Notification::make()
                            ->title('Geen keyword data provider actief')
                            ->body('Installeer een provider-package om automatisch te verrijken.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $keywords = $this->record->keywords()->pluck('keyword')->all();
                    $enriched = KeywordData::enrich($keywords, $this->record->locale ?? 'nl');
                    foreach ($enriched as $kw => $data) {
                        $attributes = array_filter($data);
                        $attributes['enriched_at'] = now();
                        $attributes['source'] = 'api';

                        Keyword::query()
                            ->where('keyword_research_id', $this->record->id)
                            ->where('keyword', $kw)
                            ->update($attributes);
                    }

                    Notification::make()
                        ->title('Verrijking klaar')
                        ->success()
                        ->send();
                }),

            Action::make('cluster')
                ->label('Cluster keywords')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Select::make('mode')
                        ->label('Modus')
                        ->options([
                            'full' => 'Herclusteren (verwijdert bestaande clusters)',
                            'incremental' => 'Inpassen in bestaande clusters',
                        ])
                        ->required()
                        ->default('incremental'),
                ])
                ->action(function (array $data): void {
                    ClusterKeywordsJob::dispatch($this->record->id, $data['mode']);

                    Notification::make()
                        ->title('Clustering gestart')
                        ->success()
                        ->send();
                }),

            Action::make('generate_drafts')
                ->label('Genereer drafts')
                ->icon('heroicon-o-document-plus')
                ->url(fn () => KeywordWorkspaceResource::getUrl('generate', ['record' => $this->record])),

            DeleteAction::make(),
        ];
    }
}
