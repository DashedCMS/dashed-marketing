<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedMarketing\Jobs\GenerateSocialImageJob;
use Dashed\DashedMarketing\Services\SubjectImageResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class GenerateImageAction extends Action
{
    /** @var array<int, array<string, string>> */
    private array $subjectImageCache = [];

    public static function getDefaultName(): ?string
    {
        return 'generateImage';
    }

    /**
     * Options keyed by image URL, value = rendered HTML with thumbnail.
     *
     * @return array<string, string>
     */
    private function subjectImageOptions($record): array
    {
        if (! $record) {
            return [];
        }

        $key = (int) ($record->id ?? 0);
        if (isset($this->subjectImageCache[$key])) {
            return $this->subjectImageCache[$key];
        }

        $subject = $record->subject;
        if (! $subject) {
            return $this->subjectImageCache[$key] = [];
        }

        $urls = array_keys(app(SubjectImageResolver::class)->collect($subject));

        $options = [];
        foreach ($urls as $url) {
            $safe = e($url);
            $options[$url] = '<div style="display:flex;align-items:center;gap:.75rem;">'
                .'<img src="'.$safe.'" style="width:48px;height:48px;object-fit:cover;border-radius:6px;flex-shrink:0;" />'
                .'<span style="font-size:.75rem;opacity:.7;word-break:break-all;">'.$safe.'</span>'
                .'</div>';
        }

        return $this->subjectImageCache[$key] = $options;
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
            ->form(fn ($record): array => [
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

                Select::make('subject_image')
                    ->label('Kies afbeelding uit gekoppeld onderwerp')
                    ->helperText('Geselecteerde afbeelding vult automatisch de referentieafbeelding URL hieronder.')
                    ->options(fn () => $this->subjectImageOptions($record))
                    ->allowHtml()
                    ->nullable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('reference_image', $state);
                        }
                    })
                    ->visible(fn () => ! empty($this->subjectImageOptions($record))),

                Placeholder::make('subject_image_preview')
                    ->label('Voorbeeld')
                    ->content(fn (callable $get) => $get('subject_image')
                        ? new HtmlString('<img src="'.e($get('subject_image')).'" style="max-height:180px;border-radius:8px;" />')
                        : '—')
                    ->visible(fn (callable $get) => (bool) $get('subject_image')),

                TextInput::make('reference_image')
                    ->label('Referentieafbeelding URL (optioneel)')
                    ->helperText('Met referentieafbeelding wordt Flux Kontext gebruikt — de input wordt 1-op-1 behouden en alleen de prompt wordt als edit-instructie toegepast.')
                    ->url()
                    ->nullable(),
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
                );

                Notification::make()
                    ->title('Afbeelding generatie gestart')
                    ->body('De afbeelding wordt op de achtergrond gegenereerd.')
                    ->success()
                    ->send();
            });
    }
}
