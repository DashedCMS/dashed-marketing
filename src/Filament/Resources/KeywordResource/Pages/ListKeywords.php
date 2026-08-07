<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages;

use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Facades\KeywordData;
use Dashed\DashedMarketing\Jobs\ClusterKeywordsJob;
use Dashed\DashedMarketing\Managers\KeywordDataManager;
use Dashed\DashedMarketing\Filament\Imports\KeywordImporter;
use Dashed\DashedMarketing\Filament\Actions\XlsxImportAction;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource;

class ListKeywords extends ListRecords
{
    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Voeg keyword toe')),

            XlsxImportAction::make('import_keywords')
                ->label(__('Importeer CSV/Excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->importer(KeywordImporter::class),

            Actions\Action::make('enrich')
                ->label(__('Verrijk via API'))
                ->icon('heroicon-o-sparkles')
                ->schema([
                    Select::make('locale')
                        ->options(['nl' => __('Nederlands'), 'en' => __('English')])
                        ->default(config('app.locale', 'nl'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $manager = app(KeywordDataManager::class);
                    if ($manager->provider()->name() === 'manual') {
                        Notification::make()
                            ->title(__('Geen keyword data provider actief'))
                            ->body(__('Installeer een provider-package om automatisch te verrijken.'))
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
                    Notification::make()->title(__('Verrijking klaar'))->success()->send();
                }),

            Actions\Action::make('cluster')
                ->label(__('Cluster keywords'))
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Select::make('locale')
                        ->options(['nl' => __('Nederlands'), 'en' => __('English')])
                        ->default(config('app.locale', 'nl'))
                        ->required(),
                    Select::make('mode')
                        ->label(__('Modus'))
                        ->options([
                            'full' => __('Herclusteren (verwijdert bestaande clusters voor deze taal)'),
                            'incremental' => __('Inpassen in bestaande clusters'),
                        ])
                        ->required()
                        ->default('incremental'),
                ])
                ->action(function (array $data) {
                    ClusterKeywordsJob::dispatch($data['locale'], $data['mode']);
                    Notification::make()->title(__('Clustering gestart'))->success()->send();
                }),

            Actions\Action::make('generate_drafts')
                ->label(__('Genereer drafts'))
                ->icon('heroicon-o-document-plus')
                ->url(fn () => KeywordResource::getUrl('generate')),
        ];
    }
}
