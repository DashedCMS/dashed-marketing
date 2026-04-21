<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedMarketing\Jobs\GenerateSocialPostJob;
use Dashed\DashedMarketing\Models\SocialChannel;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

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
                    type: $data['type'],
                    channels: $data['channels'] ?? [],
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
        $typeOptions = array_map(
            fn ($t) => $t['label'].' - '.$t['description'],
            config('dashed-marketing.types', [])
        );

        $routeModelOptions = [];
        $routeModels = cms()->builder('routeModels') ?? [];
        foreach ($routeModels as $key => $config) {
            if (isset($config['class']) && class_exists($config['class'])) {
                $routeModelOptions[$config['class']] = $config['label'] ?? class_basename($config['class']);
            }
        }

        return [
            Select::make('type')
                ->label('Type post')
                ->options($typeOptions)
                ->default('post')
                ->required()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('channels', [])),

            CheckboxList::make('channels')
                ->label('Kanalen')
                ->helperText('Kies één of meer kanalen waar deze post op geplaatst kan worden. Alleen kanalen die het gekozen type accepteren worden getoond.')
                ->options(function (callable $get): array {
                    $type = $get('type') ?: 'post';

                    return SocialChannel::query()
                        ->where('is_active', true)
                        ->orderBy('order')
                        ->get()
                        ->filter(fn (SocialChannel $ch) => in_array($type, $ch->accepted_types ?? [], true))
                        ->pluck('name', 'slug')
                        ->toArray();
                })
                ->columns(2)
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
                ->searchable()
                ->getSearchResultsUsing(function (string $search, callable $get) {
                    $class = $get('subject_model_class');
                    if (! $class || ! class_exists($class)) {
                        return [];
                    }

                    $query = $class::query();
                    $model = new $class;
                    $columns = [];
                    foreach (['name', 'title'] as $column) {
                        if (Schema::hasColumn($model->getTable(), $column)) {
                            $columns[] = $column;
                        }
                    }

                    if (empty($columns)) {
                        $query->where($model->getKeyName(), 'like', "%{$search}%");
                    } else {
                        $query->where(function ($q) use ($columns, $search) {
                            foreach ($columns as $column) {
                                $q->orWhere($column, 'like', "%{$search}%");
                            }
                        });
                    }

                    return $query->limit(500)->get()->mapWithKeys(
                        fn ($item) => [$item->getKey() => $item->name ?? $item->title ?? "#{$item->getKey()}"]
                    )->toArray();
                })
                ->getOptionLabelUsing(function ($value, callable $get) {
                    $class = $get('subject_model_class');
                    if (! $value || ! $class || ! class_exists($class)) {
                        return null;
                    }

                    $item = $class::find($value);
                    if (! $item) {
                        return null;
                    }

                    return $item->name ?? $item->title ?? "#{$item->getKey()}";
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
