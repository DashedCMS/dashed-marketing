<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages;

use Dashed\DashedMarketing\Facades\KeywordData;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource;
use Dashed\DashedMarketing\Jobs\ClusterKeywordsJob;
use Dashed\DashedMarketing\Managers\KeywordDataManager;
use Dashed\DashedMarketing\Models\Keyword;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListKeywords extends ListRecords
{
    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Voeg keyword toe'),

            Actions\Action::make('import_csv')
                ->label('Importeer CSV/Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn () => KeywordResource::getUrl('import')),

            Actions\Action::make('enrich')
                ->label('Verrijk via API')
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Select::make('locale')
                        ->options(['nl' => 'Nederlands', 'en' => 'English'])
                        ->default(config('app.locale', 'nl'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $manager = app(KeywordDataManager::class);
                    if ($manager->provider()->name() === 'manual') {
                        Notification::make()
                            ->title('Geen keyword data provider actief')
                            ->body('Installeer een provider-package om automatisch te verrijken.')
                            ->warning()
                            ->send();

                        return;
                    }
                    $keywords = Keyword::where('locale', $data['locale'])->pluck('keyword')->all();
                    $enriched = KeywordData::enrich($keywords, $data['locale']);
                    foreach ($enriched as $kw => $row) {
                        Keyword::query()
                            ->where('locale', $data['locale'])
                            ->where('keyword', $kw)
                            ->update(array_filter($row) + ['enriched_at' => now(), 'source' => 'api']);
                    }
                    Notification::make()->title('Verrijking klaar')->success()->send();
                }),

            Actions\Action::make('cluster')
                ->label('Cluster keywords')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Select::make('locale')
                        ->options(['nl' => 'Nederlands', 'en' => 'English'])
                        ->default(config('app.locale', 'nl'))
                        ->required(),
                    Select::make('mode')
                        ->label('Modus')
                        ->options([
                            'full' => 'Herclusteren (verwijdert bestaande clusters voor deze taal)',
                            'incremental' => 'Inpassen in bestaande clusters',
                        ])
                        ->required()
                        ->default('incremental'),
                ])
                ->action(function (array $data) {
                    ClusterKeywordsJob::dispatch($data['locale'], $data['mode']);
                    Notification::make()->title('Clustering gestart')->success()->send();
                }),

            Actions\Action::make('generate_drafts')
                ->label('Genereer drafts')
                ->icon('heroicon-o-document-plus')
                ->url(fn () => KeywordResource::getUrl('generate')),
        ];
    }
}
