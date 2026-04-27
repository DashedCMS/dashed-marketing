<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Jobs\GenerateSocialImageJob;
use Dashed\DashedMarketing\Services\SubjectImageResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as SchemaActions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
     * Build a short textual context block describing the linked subject so the
     * AI prompt generator can tailor prompts to the actual page/product content.
     */
    private function buildSubjectContext($record, ?string $subjectType, $subjectId): string
    {
        $subject = $this->resolveSubject($record, $subjectType, $subjectId);

        if (! $subject) {
            return '';
        }

        $skip = ['id', 'created_at', 'updated_at', 'deleted_at', 'site_id', 'password', 'remember_token', 'api_token'];
        $attributes = $subject->attributesToArray();

        $name = $attributes['name'] ?? $attributes['title'] ?? (class_basename($subject).' #'.$subject->getKey());
        $name = is_array($name) ? (reset($name) ?: class_basename($subject)) : $name;

        $lines = [
            'Type: '.class_basename($subject),
            'Naam: '.(string) $name,
        ];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $value = trim(strip_tags((string) $value));
            if ($value === '') {
                continue;
            }

            $value = Str::limit($value, 500);
            $lines[] = "{$key}: {$value}";
        }

        return implode("\n", $lines);
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
    private function generateDistinctImagePrompts($record, int $count, ?string $subjectType = null, $subjectId = null, ?string $userInstructions = null): array
    {
        if (! $record || $count < 1) {
            return [];
        }

        $caption = trim((string) ($record->caption ?? ''));
        $seed = trim((string) ($record->image_prompt ?? ''));
        $altText = trim((string) ($record->alt_text ?? ''));

        $count = max(1, min(6, $count));

        $subjectContext = $this->buildSubjectContext($record, $subjectType, $subjectId);

        $subjectSection = $subjectContext !== ''
            ? "\n\nGekoppeld onderwerp (de afbeelding moet passen bij de inhoud van dit item):\n{$subjectContext}"
            : '';

        $userSection = ($userInstructions !== null && trim($userInstructions) !== '')
            ? "\n\nGebruikersinstructies (verwerk deze volledig in elke prompt):\n".trim($userInstructions)
            : '';

        $prompt = <<<PROMPT
        Generate {$count} strongly distinct, production-grade image prompts in ENGLISH for a single social media post.

        ## Quality bar (NON-NEGOTIABLE)
        Each prompt MUST read like a senior product photographer's brief. That means:
        - Open with a concrete style declaration: "Photorealistic product photo of...", "Cinematic lifestyle shot of...", "Editorial flat-lay of...", "Documentary candid of...", "High-end studio still life of..." — pick what fits the brand.
        - Name the SUBJECT precisely (what is in frame, how many, in what arrangement).
        - Name a concrete SETTING (not "background" — give a real place: e.g. "wooden table outside a typical Dutch canal house", "linen-covered marble countertop", "sunlit bedroom with sheer curtains").
        - Name 3+ concrete PROPS / scene elements that reinforce the post's theme. If the user mentions a holiday, season or event, EXPAND it into specific iconography (e.g. King's Day → "small orange tulips, a tiny Dutch flag, orange streamers, orange crown decorations", Christmas → "pine sprigs, red berries, beeswax candles, linen napkins"). Never use vague phrases like "festive decorations" or "subtle accents".
        - Specify LIGHTING with a real time-of-day or quality (e.g. "soft natural daylight", "golden hour", "moody overcast", "warm tungsten side-light").
        - Specify CAMERA / OPTICS: lens (e.g. "50mm", "85mm macro", "35mm wide"), depth of field (e.g. "shallow depth of field", "everything in sharp focus"), framing (e.g. "close-up", "three-quarter angle", "overhead flat-lay").
        - End with a short BRAND VIBE (e.g. "Cozy, premium lifestyle vibe", "minimalist Scandinavian, calm and quiet", "bold and energetic streetwear").
        - No text in the image unless the user explicitly asked for it.
        - Each prompt should be ~50-120 words, dense and concrete. NEVER produce a generic abstract prompt with words like "stylish", "elegant", "modern aesthetic" without grounding them in concrete visuals.

        ## Variation rule
        Across the {$count} prompts, vary composition, angle, setting and light — but keep brand and subject coherent.

        ## Inputs
        Post caption:
        "{$caption}"

        Alt text:
        "{$altText}"

        Seed prompt (use as a starting point, do not just copy):
        "{$seed}"{$subjectSection}{$userSection}

        If the user instructions above are short or thematic (e.g. just "King's Day", "for spring", "promote the new bundle"), you MUST EXPAND them into a full concrete scene per the quality bar — never echo a short brief back as the prompt.

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
                                    if (Schema::hasColumn($model->getTable(), $col)) {
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
                        : '-')
                    ->visible(fn (callable $get) => (bool) $get('subject_image')),

                TextInput::make('reference_image')
                    ->label('Referentieafbeelding URL (optioneel)')
                    ->helperText('Met referentieafbeelding wordt fal nano-banana/edit gebruikt - de input wordt 1-op-1 behouden.')
                    ->url()
                    ->nullable(),

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

                SchemaActions::make([
                    Action::make('aiFillSamePrompt')
                        ->label('Vul prompt met AI')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->visible(fn () => Ai::hasProvider())
                        ->modalHeading('Image prompt genereren met AI')
                        ->modalSubmitActionLabel('Genereer')
                        ->schema([
                            Textarea::make('instructions')
                                ->label('Instructies voor de afbeelding (optioneel)')
                                ->placeholder('Bijv: cinematisch, donkere achtergrond, close-up op product, geen mensen in beeld')
                                ->helperText('Hoe gedetailleerder, hoe beter de prompt aansluit op wat je wilt zien.')
                                ->rows(4),
                        ])
                        ->action(function (array $data, callable $get, callable $set) use ($record): void {
                            $instructionParts = array_filter([
                                (string) ($get('same_prompt_text') ?? ''),
                                (string) ($data['instructions'] ?? ''),
                            ], fn ($v) => trim($v) !== '');

                            $userInstructions = implode("\n\n", $instructionParts);

                            $prompts = $this->generateDistinctImagePrompts(
                                $record,
                                1,
                                $get('subject_type'),
                                $get('subject_id'),
                                $userInstructions,
                            );

                            $generated = $prompts[0] ?? null;
                            if (! $generated) {
                                Notification::make()
                                    ->title('AI gaf geen geldige prompt terug')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $set('same_prompt_text', $generated);

                            if ($record) {
                                $record->update(['image_prompt' => $generated]);
                            }

                            Notification::make()
                                ->title($record ? 'Prompt ingevuld en opgeslagen' : 'Prompt ingevuld')
                                ->body($record ? null : 'Sla de post op om de prompt permanent te bewaren.')
                                ->success()
                                ->send();
                        }),
                ])
                    ->visible(fn (callable $get) => (bool) $get('same_prompt')),

                Textarea::make('same_prompt_text')
                    ->label('Image prompt')
                    ->helperText('Wordt gebruikt voor elke gegenereerde afbeelding. Zelf invullen om eigen instructies mee te geven, of gebruik de knop "Vul prompt met AI" om een suggestie te laten maken op basis van de caption en eventuele extra instructies.')
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
                        ->modalHeading('Image prompts genereren met AI')
                        ->modalSubmitActionLabel('Genereer')
                        ->schema([
                            Textarea::make('instructions')
                                ->label('Instructies voor de afbeeldingen (optioneel)')
                                ->placeholder('Bijv: cinematisch, donkere achtergrond, close-up op product, geen mensen in beeld')
                                ->helperText('Wordt voor alle prompts gebruikt. Bestaande tekst in de promptvelden wordt ook meegenomen als context.')
                                ->rows(4),
                        ])
                        ->action(function (array $data, callable $get, callable $set) use ($record): void {
                            $count = (int) ($get('image_count') ?: 1);
                            $existingPrompts = (array) ($get('prompts') ?? []);
                            $existingText = collect($existingPrompts)
                                ->map(fn ($row) => trim((string) ($row['text'] ?? '')))
                                ->filter()
                                ->implode("\n\n");

                            $instructionParts = array_filter([
                                $existingText,
                                (string) ($data['instructions'] ?? ''),
                            ], fn ($v) => trim($v) !== '');

                            $userInstructions = implode("\n\n", $instructionParts);

                            $prompts = $this->generateDistinctImagePrompts(
                                $record,
                                $count,
                                $get('subject_type'),
                                $get('subject_id'),
                                $userInstructions,
                            );

                            if (empty($prompts)) {
                                Notification::make()
                                    ->title('AI gaf geen geldige prompts terug')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $set('prompts', array_map(fn ($p) => ['text' => $p], $prompts));

                            // Persist the first prompt as the post's seed image_prompt so it survives modal close.
                            if ($record && ! empty($prompts[0])) {
                                $record->update(['image_prompt' => $prompts[0]]);
                            }

                            Notification::make()
                                ->title($record ? 'Prompts ingevuld en eerste opgeslagen als seed' : 'Prompts ingevuld')
                                ->body($record ? null : 'Sla de post op om de prompts permanent te bewaren.')
                                ->success()
                                ->send();
                        }),
                ])
                    ->visible(fn (callable $get) => ! (bool) $get('same_prompt')),

                Repeater::make('prompts')
                    ->label('Prompts per afbeelding')
                    ->helperText('Eén prompt per afbeelding. Gebruik de knop "Vul prompts met AI" hierboven om alle velden in één keer door AI te laten suggereren op basis van de caption en de inhoud van het gekoppelde onderwerp.')
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
                        ->body('Er zijn geen prompts om te genereren - vul ze in of zet "Zelfde prompt" aan.')
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
