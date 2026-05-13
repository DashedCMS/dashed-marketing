<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Dashed\DashedMarketing\Jobs\GenerateMetaForRecordJob;

/**
 * Header-action die op elke CMS editpage via HasEditableCMSActions
 * beschikbaar is. Dispatcht een job die voor elke taal een meta-titel en
 * meta-omschrijving genereert.
 */
class GenerateMetaForAllLocalesAction
{
    public static function make(): Action
    {
        return Action::make('generate_meta_all_locales')
            ->label('Genereer meta (alle talen)')
            ->icon('heroicon-o-language')
            ->color('info')
            ->visible(function ($livewire) {
                $record = $livewire?->record ?? null;

                return $record !== null && $record->exists;
            })
            ->modalHeading('Meta genereren voor alle talen')
            ->modalDescription('AI genereert een meta-titel en meta-omschrijving voor elk geconfigureerde taal. Dit gebeurt op de achtergrond.')
            ->modalSubmitActionLabel('Start generatie')
            ->schema([
                Textarea::make('user_instruction')
                    ->label('Instructie (optioneel)')
                    ->placeholder('Bijv. focus op het keyword "fietsverzekering".')
                    ->rows(3),
                Toggle::make('overwrite')
                    ->label('Overschrijf bestaande meta')
                    ->helperText('Standaard worden alleen lege velden ingevuld.')
                    ->default(false),
            ])
            ->action(function (array $data, $livewire): void {
                $record = $livewire?->record ?? null;
                if (! $record || ! $record->exists) {
                    Notification::make()
                        ->title('Geen record om te verwerken')
                        ->danger()
                        ->send();

                    return;
                }

                $instruction = ! empty($data['user_instruction']) ? (string) $data['user_instruction'] : null;
                $overwrite = (bool) ($data['overwrite'] ?? false);

                GenerateMetaForRecordJob::dispatch(
                    $record::class,
                    $record->getKey(),
                    $instruction,
                    $overwrite,
                );

                Notification::make()
                    ->title('Meta wordt gegenereerd')
                    ->body('Wordt op de achtergrond gegenereerd voor alle talen. Ververs deze pagina zodra de job klaar is om het resultaat te zien.')
                    ->success()
                    ->send();
            });
    }
}
