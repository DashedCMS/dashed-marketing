<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedMarketing\Jobs\GenerateSocialImageJob;
use Dashed\DashedMarketing\Services\SubjectImageResolver;
use Dashed\DashedAi\Facades\Ai;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Illuminate\Support\HtmlString;

class GenerateImageAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generateImage';
    }

    private function resolveSubject($record, ?string $type, $id)
    {
        if ($type && $id && class_exists($type)) {
            return $type::find($id);
        }

        return $record?->subject;
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

    /**
     * Ask the AI provider for N distinct image prompts based on the post's caption
     * and the post's image_prompt as the seed.
     *
     * @return array<int, string>
     */
    private function generateDistinctImagePrompts($record, int $count): array
    {
        if (! $record || $count < 1) {
            return [];
        }

        $caption = trim((string) ($record->caption ?? ''));
        $seed = trim((string) ($record->image_prompt ?? ''));
        $altText = trim((string) ($record->alt_text ?? ''));

        $count = max(1, min(6, $count));

        $prompt = <<<PROMPT
        Genereer {$count} sterk verschillende image prompts in het Engels voor één social media post.
        Elke prompt moet visueel een ander invalshoek pakken (compositie, perspectief, lighting, setting, sfeer)
        maar visueel passen bij hetzelfde merk en dezelfde post-boodschap. Beschrijf subject, compositie, lighting,
        color palette, mood en stijl. Geen tekst in het beeld tenzij expliciet gevraagd.

        Caption van de post:
        "{$caption}"

        Alt-tekst:
        "{$altText}"

        Basis prompt (gebruik als startpunt, varieer eromheen):
        "{$seed}"

        Retourneer UITSLUITEND geldig JSON in dit formaat:
        {
            "prompts": [
                "English image prompt 1",
                "English image prompt 2"
            ]
        }
        PROMPT;

        try {
            $result = Ai::json($prompt);
        } catch (\Throwable $e) {
            return [];
        }

        $prompts = $result['prompts'] ?? null;
        if (! is_array($prompts)) {
            return [];
        }

        $prompts = array_values(array_filter(array_map(
            fn ($p) => is_string($p) ? trim($p) : null,
            $prompts,
        )));

        return array_slice($prompts, 0, $count);
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
            ->modalWidth('3xl')
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

                Select::make('image_count')
                    ->label('Aantal afbeeldingen')
                    ->options(array_combine(range(1, 6), range(1, 6)))
                    ->default(1)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                        if ((bool) $get('same_prompt')) {
                            return;
                        }

                        $existing = (array) ($get('prompts') ?? []);
                        $count = (int) $state;
                        $existing = array_values($existing);

                        if (count($existing) > $count) {
                            $existing = array_slice($existing, 0, $count);
                        } else {
                            while (count($existing) < $count) {
                                $existing[] = ['text' => ''];
                            }
                        }

                        $set('prompts', $existing);
                    }),

                Toggle::make('same_prompt')
                    ->label('Zelfde prompt voor alle afbeeldingen')
                    ->default(true)
                    ->live()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) use ($record): void {
                        if ($state) {
                            return;
                        }
                        $count = (int) ($get('image_count') ?: 1);
                        $base = (string) ($record?->image_prompt ?? '');
                        $set('prompts', array_fill(0, max(1, $count), ['text' => $base]));
                    }),

                Textarea::make('same_prompt_text')
                    ->label('Image prompt')
                    ->helperText('Wordt gebruikt voor elke gegenereerde afbeelding.')
                    ->default(fn () => (string) ($record?->image_prompt ?? ''))
                    ->rows(3)
                    ->required(fn (callable $get) => (bool) $get('same_prompt'))
                    ->visible(fn (callable $get) => (bool) $get('same_prompt')),

                SchemaActions::make([
                    Action::make('aiFillPrompts')
                        ->label('Vul prompts met AI')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->visible(fn () => Ai::hasProvider())
                        ->action(function (callable $get, callable $set) use ($record): void {
                            $count = (int) ($get('image_count') ?: 1);
                            $prompts = $this->generateDistinctImagePrompts($record, $count);

                            if (empty($prompts)) {
                                Notification::make()
                                    ->title('AI gaf geen geldige prompts terug')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $set('prompts', array_map(fn ($p) => ['text' => $p], $prompts));

                            Notification::make()
                                ->title('Prompts ingevuld')
                                ->success()
                                ->send();
                        }),
                ])
                    ->visible(fn (callable $get) => ! (bool) $get('same_prompt')),

                Repeater::make('prompts')
                    ->label('Prompts per afbeelding')
                    ->helperText('Eén prompt per afbeelding. Gebruik de knop "Vul prompts met AI" hierboven om alle velden in één keer door AI te laten suggereren op basis van de caption en context.')
                    ->schema([
                        Textarea::make('text')
                            ->label('Prompt')
                            ->rows(3)
                            ->required(),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->visible(fn (callable $get) => ! (bool) $get('same_prompt')),

                Select::make('subject_type')
                    ->label('Onderwerp type')
                    ->helperText('Kies het type om een gekoppeld item en zijn afbeeldingen te gebruiken.')
                    ->options($this->routeModelOptions())
                    ->default(fn () => $record?->subject_type)
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (callable $set) {
                        $set('subject_id', null);
                        $set('subject_image', null);
                    }),

                Select::make('subject_id')
                    ->label('Specifiek onderwerp')
                    ->nullable()
                    ->searchable()
                    ->live()
                    ->default(fn () => $record?->subject_id)
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
                    ->visible(fn (callable $get) => (bool) $get('subject_type')),

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

                if (! empty($data['subject_type']) && ! empty($data['subject_id'])) {
                    $class = $data['subject_type'];
                    if (class_exists($class) && $class::query()->whereKey($data['subject_id'])->exists()) {
                        if ((string) $record->subject_type !== (string) $class || (int) $record->subject_id !== (int) $data['subject_id']) {
                            $record->update([
                                'subject_type' => $class,
                                'subject_id' => $data['subject_id'],
                            ]);
                        }
                    }
                }

                $count = max(1, (int) ($data['image_count'] ?? 1));
                $samePrompt = (bool) ($data['same_prompt'] ?? true);

                if ($samePrompt) {
                    $promptText = trim((string) ($data['same_prompt_text'] ?? ''));
                    if (! $promptText) {
                        $promptText = (string) $record->image_prompt;
                    }
                    $promptList = array_fill(0, $count, $promptText);
                } else {
                    $promptList = collect($data['prompts'] ?? [])
                        ->map(fn ($row) => trim((string) ($row['text'] ?? '')))
                        ->filter()
                        ->values()
                        ->all();
                }

                if (empty(array_filter($promptList))) {
                    Notification::make()
                        ->title('Geen prompts')
                        ->body('Er zijn geen prompts om te genereren — vul ze in of zet "Zelfde prompt" aan.')
                        ->warning()
                        ->send();

                    return;
                }

                foreach ($promptList as $singlePrompt) {
                    GenerateSocialImageJob::dispatch(
                        post: $record,
                        ratio: $data['ratio'],
                        stylePreset: $data['style_preset'],
                        referenceImageUrl: $data['reference_image'] ?? null,
                        promptOverride: $singlePrompt,
                    );
                }

                Notification::make()
                    ->title(count($promptList).' afbeelding(en) in de wachtrij')
                    ->body('De afbeeldingen worden op de achtergrond gegenereerd en aan deze post toegevoegd.')
                    ->success()
                    ->send();
            });
    }
}
