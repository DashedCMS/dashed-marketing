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
    public static function getDefaultName(): ?string
    {
        return 'generateImage';
    }

    private function resolveSubject($record, ?string $type, $id)
    {
        if ($record?->subject) {
            return $record->subject;
        }

        if ($type && $id && class_exists($type)) {
            return $type::find($id);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function buildImageOptions($subject): array
    {
        if (! $subject) {
            return [];
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

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function routeModelOptions(): array
    {
        $options = [];
        foreach (cms()->builder('routeModels') ?? [] as $modelConfig) {
            $class = $modelConfig['class'] ?? null;
            if ($class && class_exists($class)) {
                $options[$class] = $modelConfig['name'] ?? class_basename($class);
            }
        }

        return $options;
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

                Select::make('subject_type')
                    ->label('Onderwerp type')
                    ->helperText('Geen onderwerp gekoppeld aan deze post — kies hier een type om zijn afbeeldingen te gebruiken.')
                    ->options($this->routeModelOptions())
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (callable $set) {
                        $set('subject_id', null);
                        $set('subject_image', null);
                    })
                    ->visible(fn () => ! $record?->subject),

                Select::make('subject_id')
                    ->label('Specifiek onderwerp')
                    ->nullable()
                    ->searchable()
                    ->live()
                    ->getSearchResultsUsing(function (string $search, callable $get) {
                        $class = $get('subject_type');
                        if (! $class || ! class_exists($class)) {
                            return [];
                        }

                        $model = new $class;

                        return $class::query()
                            ->where(function ($q) use ($search, $model) {
                                foreach (['name', 'title'] as $col) {
                                    if (\Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), $col)) {
                                        $q->orWhere($col, 'like', "%{$search}%");
                                    }
                                }
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($m) => [$m->getKey() => $m->name ?? $m->title ?? "#{$m->getKey()}"])
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value, callable $get) {
                        $class = $get('subject_type');
                        if (! $value || ! $class || ! class_exists($class)) {
                            return null;
                        }
                        $item = $class::find($value);

                        return $item ? ($item->name ?? $item->title ?? "#{$item->getKey()}") : null;
                    })
                    ->afterStateUpdated(fn (callable $set) => $set('subject_image', null))
                    ->visible(fn (callable $get) => ! $record?->subject && (bool) $get('subject_type')),

                Select::make('subject_image')
                    ->label('Kies afbeelding uit onderwerp')
                    ->helperText('Geselecteerde afbeelding vult automatisch de referentieafbeelding URL hieronder.')
                    ->options(function (callable $get) use ($record) {
                        $subject = $this->resolveSubject($record, $get('subject_type'), $get('subject_id'));

                        return $this->buildImageOptions($subject);
                    })
                    ->allowHtml()
                    ->nullable()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('reference_image', $state);
                        }
                    })
                    ->visible(function (callable $get) use ($record) {
                        $subject = $this->resolveSubject($record, $get('subject_type'), $get('subject_id'));

                        return ! empty($this->buildImageOptions($subject));
                    }),

                Placeholder::make('subject_image_preview')
                    ->label('Voorbeeld')
                    ->content(fn (callable $get) => $get('subject_image')
                        ? new HtmlString('<img src="'.e($get('subject_image')).'" style="max-height:180px;border-radius:8px;" />')
                        : '—')
                    ->visible(fn (callable $get) => (bool) $get('subject_image')),

                TextInput::make('reference_image')
                    ->label('Referentieafbeelding URL (optioneel)')
                    ->helperText('Met referentieafbeelding wordt fal nano-banana/edit gebruikt — de input wordt 1-op-1 behouden.')
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

                if (! $record->subject && ! empty($data['subject_type']) && ! empty($data['subject_id'])) {
                    $class = $data['subject_type'];
                    if (class_exists($class) && $class::query()->whereKey($data['subject_id'])->exists()) {
                        $record->update([
                            'subject_type' => $class,
                            'subject_id' => $data['subject_id'],
                        ]);
                    }
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
