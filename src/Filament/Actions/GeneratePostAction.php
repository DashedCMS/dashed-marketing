<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedMarketing\Jobs\GenerateSocialPostJob;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class GeneratePostAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generatePost';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Genereer post met AI')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->form($this->buildForm())
            ->action(function (array $data): void {
                $subject = null;
                if (! empty($data['subject_model_class']) && ! empty($data['subject_model_id'])) {
                    $class = $data['subject_model_class'];
                    if (class_exists($class)) {
                        $subject = $class::find($data['subject_model_id']);
                    }
                }

                GenerateSocialPostJob::dispatch(
                    platform: $data['platform'],
                    subject: $subject,
                    pillarId: $data['pillar_id'] ?? null,
                    campaignId: $data['campaign_id'] ?? null,
                    toneOverride: $data['tone_override'] ?? null,
                    extraInstructions: $data['extra_instructions'] ?? null,
                    includeKeywords: (bool) ($data['include_keywords'] ?? false),
                    scheduledAt: $data['scheduled_at'] ?? null,
                    siteId: Sites::getActive(),
                );

                Notification::make()
                    ->title('Post generatie gestart')
                    ->body('De post wordt op de achtergrond aangemaakt.')
                    ->success()
                    ->send();
            });
    }

    protected function buildForm(): array
    {
        $platformOptions = array_map(
            fn ($p) => $p['label'],
            config('dashed-marketing.platforms', [])
        );

        $routeModelOptions = [];
        $routeModels = cms()->builder('routeModels') ?? [];
        foreach ($routeModels as $key => $config) {
            if (isset($config['class']) && class_exists($config['class'])) {
                $routeModelOptions[$config['class']] = $config['label'] ?? class_basename($config['class']);
            }
        }

        return [
            Select::make('platform')
                ->label('Platform')
                ->options($platformOptions)
                ->required(),

            Select::make('subject_model_class')
                ->label('Onderwerp type')
                ->options($routeModelOptions)
                ->nullable()
                ->reactive()
                ->placeholder('Geen specifiek onderwerp'),

            Select::make('subject_model_id')
                ->label('Specifiek onderwerp')
                ->nullable()
                ->placeholder('Selecteer een item...')
                ->options(function (callable $get) {
                    $class = $get('subject_model_class');
                    if (! $class || ! class_exists($class)) {
                        return [];
                    }

                    return $class::query()->limit(100)->get()->mapWithKeys(
                        fn ($item) => [$item->id => $item->name ?? $item->title ?? "#{$item->id}"]
                    )->toArray();
                })
                ->visible(fn (callable $get) => (bool) $get('subject_model_class')),

            Select::make('pillar_id')
                ->label('Content pijler')
                ->relationship('pillar', 'name')
                ->nullable(),

            Select::make('campaign_id')
                ->label('Campagne')
                ->relationship('campaign', 'name')
                ->nullable(),

            TextInput::make('tone_override')
                ->label('Toon override')
                ->placeholder('Bijv: grappig en informeel')
                ->nullable(),

            DateTimePicker::make('scheduled_at')
                ->label('Inplannen op')
                ->nullable(),

            Toggle::make('include_keywords')
                ->label('Verwerk goedgekeurde keywords')
                ->default(false),

            Textarea::make('extra_instructions')
                ->label('Extra instructies')
                ->rows(3)
                ->nullable(),
        ];
    }
}
