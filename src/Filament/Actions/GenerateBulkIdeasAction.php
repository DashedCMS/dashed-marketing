<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedMarketing\Jobs\GenerateBulkSocialIdeasJob;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class GenerateBulkIdeasAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateBulkIdeas';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Genereer ideeën met AI')
            ->icon('heroicon-o-sparkles')
            ->color('warning')
            ->form([
                Select::make('period')
                    ->label('Periode')
                    ->options([
                        1 => '1 week',
                        2 => '2 weken',
                        4 => '4 weken',
                    ])
                    ->default(2)
                    ->required(),

                TextInput::make('count')
                    ->label('Aantal ideeën')
                    ->numeric()
                    ->minValue(3)
                    ->maxValue(30)
                    ->default(10)
                    ->required(),

                Textarea::make('focus')
                    ->label('Focus / thema (optioneel)')
                    ->placeholder('Bijv: zomercollectie, Black Friday, duurzaamheid...')
                    ->rows(2)
                    ->nullable(),
            ])
            ->action(function (array $data): void {
                GenerateBulkSocialIdeasJob::dispatch(
                    (int) $data['period'],
                    (int) $data['count'],
                    $data['focus'] ?? null,
                    auth()->id(),
                )->onQueue('ai');

                Notification::make()
                    ->title('Genereren gestart — ideeën verschijnen zodra de job klaar is')
                    ->success()
                    ->send();
            });
    }
}
