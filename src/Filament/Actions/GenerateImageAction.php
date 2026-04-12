<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedMarketing\Jobs\GenerateSocialImageJob;

class GenerateImageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateImage';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $ratioOptions = collect(config('dashed-marketing.image_generation.ratios', ['4:5', '1:1', '9:16', '2:3']))
            ->mapWithKeys(fn ($r) => [$r => $r])
            ->toArray();

        $styleOptions = config('dashed-marketing.image_generation.style_presets', []);

        $this->label('Genereer afbeelding met AI')
            ->icon('heroicon-o-photo')
            ->color('info')
            ->form([
                Select::make('ratio')
                    ->label('Beeldverhouding')
                    ->options($ratioOptions)
                    ->default('4:5')
                    ->required(),

                Select::make('style_preset')
                    ->label('Stijl')
                    ->options($styleOptions)
                    ->default('lifestyle')
                    ->required(),

                TextInput::make('reference_image')
                    ->label('Referentieafbeelding URL (optioneel)')
                    ->url()
                    ->nullable(),

                Select::make('reference_strength')
                    ->label('Referentiesterkte')
                    ->options([
                        '0.3' => 'Zwak (0.3)',
                        '0.5' => 'Gemiddeld (0.5)',
                        '0.7' => 'Sterk (0.7)',
                        '0.9' => 'Zeer sterk (0.9)',
                    ])
                    ->default(config('dashed-marketing.image_generation.default_strength', 0.7))
                    ->visible(fn (callable $get) => (bool) $get('reference_image')),
            ])
            ->action(function (array $data, $record): void {
                if (! $record) {
                    Notification::make()
                        ->title('Geen post geselecteerd')
                        ->danger()
                        ->send();

                    return;
                }

                if (! $record->image_prompt) {
                    Notification::make()
                        ->title('Geen afbeelding prompt')
                        ->body('Sla de post eerst op met een AI-gegenereerde caption om een afbeelding prompt te krijgen.')
                        ->warning()
                        ->send();

                    return;
                }

                GenerateSocialImageJob::dispatch(
                    post: $record,
                    ratio: $data['ratio'],
                    stylePreset: $data['style_preset'],
                    referenceImageUrl: $data['reference_image'] ?? null,
                    referenceStrength: (float) ($data['reference_strength'] ?? 0.7),
                );

                Notification::make()
                    ->title('Afbeelding generatie gestart')
                    ->body('De afbeelding wordt op de achtergrond gegenereerd.')
                    ->success()
                    ->send();
            });
    }
}
