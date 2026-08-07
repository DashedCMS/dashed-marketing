<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedMarketing\Jobs\GenerateBulkSocialIdeasJob;

class GenerateBulkIdeasAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateBulkIdeas';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Genereer ideeën met AI'))
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->form([
                Select::make('period')
                    ->label(__('Periode'))
                    ->options([
                        1 => __('1 week'),
                        2 => __('2 weken'),
                        4 => __('4 weken'),
                    ])
                    ->default(2)
                    ->required(),

                TextInput::make('count')
                    ->label(__('Aantal ideeën'))
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(30)
                    ->default(10)
                    ->required(),

                Textarea::make('focus')
                    ->label(__('Focus / thema (optioneel)'))
                    ->placeholder(__('Bijv: zomercollectie, Black Friday, duurzaamheid...'))
                    ->rows(2)
                    ->nullable(),
            ])
            ->action(function (array $data): void {
                GenerateBulkSocialIdeasJob::dispatch(
                    (int) $data['period'],
                    (int) $data['count'],
                    $data['focus'] ?? null,
                    auth()->id(),
                );

                Notification::make()
                    ->title(__('Genereren gestart - ideeën verschijnen zodra de AI assistent klaar is'))
                    ->success()
                    ->send();
            });
    }
}
