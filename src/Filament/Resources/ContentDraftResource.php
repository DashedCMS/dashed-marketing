<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\CreateContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\EditContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\ListContentDrafts;
use Dashed\DashedMarketing\Jobs\GenerateSectionBodyJob;
use Dashed\DashedMarketing\Jobs\RegenerateSectionHeadingJob;
use Dashed\DashedMarketing\Models\ContentDraft;
use Dashed\DashedMarketing\Services\LinkCandidatesService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class ContentDraftResource extends Resource
{
    protected static ?string $model = ContentDraft::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Content concepten';

    protected static ?string $label = 'Content concept';

    protected static ?string $pluralLabel = 'Content concepten';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Placeholder::make('live_status_poller')
                ->label('')
                ->visible(fn ($record) => $record?->status === 'writing')
                ->content(fn ($record) => new HtmlString('<div wire:poll.5s="pollDraft" class="rounded-md bg-info-50 dark:bg-info-900/20 border border-info-200 dark:border-info-800 p-3 text-sm text-info-700 dark:text-info-300"><strong>Bezig met schrijven…</strong> bodies worden op de achtergrond gegenereerd. Deze pagina ververst automatisch elke 5 seconden tot het klaar is.</div>'))
                ->columnSpanFull(),

            Section::make('Algemeen')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Titel (H1)')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if (empty($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('meta_title')
                        ->label('Meta title (SEO)')
                        ->helperText('50-60 tekens. Wordt bij publiceren naar de metadata-title van het doelrecord geschreven.')
                        ->maxLength(150)
                        ->nullable(),
                    Textarea::make('meta_description')
                        ->label('Meta description (SEO)')
                        ->helperText('140-160 tekens. Wordt bij publiceren naar de metadata-description van het doelrecord geschreven.')
                        ->rows(2)
                        ->maxLength(250)
                        ->nullable(),
                    Select::make('subject_type')
                        ->label('Target type')
                        ->options(function () {
                            $options = [];
                            try {
                                foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                                    $name = is_array($entry) ? ($entry['name'] ?? $key) : $key;
                                    $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                                    if ($class) {
                                        $options[$class] = $name;
                                    }
                                }
                            } catch (\Throwable) {
                                //
                            }

                            return $options;
                        })
                        ->nullable()
                        ->live(),
                    Select::make('subject_id')
                        ->label('Target record')
                        ->placeholder('Nieuw record aanmaken')
                        ->options(function ($get) {
                            $class = $get('subject_type');
                            if (! $class || ! class_exists($class)) {
                                return [];
                            }
                            try {
                                return $class::query()->limit(50)->get()->mapWithKeys(function ($m) {
                                    $name = $m->name ?? $m->title ?? 'Record #'.$m->getKey();
                                    if (is_array($name)) {
                                        $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #'.$m->getKey());
                                    }

                                    return [$m->getKey() => $name];
                                })->all();
                            } catch (\Throwable) {
                                return [];
                            }
                        })
                        ->nullable(),
                    Select::make('locale')
                        ->label('Taal')
                        ->options([
                            'nl' => 'Nederlands',
                            'en' => 'Engels',
                            'de' => 'Duits',
                            'fr' => 'Frans',
                        ])
                        ->required()
                        ->default('nl'),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'concept' => 'Concept',
                            'pending' => 'In wachtrij',
                            'planning' => 'Planning...',
                            'writing' => 'Schrijven...',
                            'ready' => 'Klaar',
                            'applied' => 'Toegepast',
                            'failed' => 'Mislukt',
                        ])
                        ->default('concept')
                        ->disabled(),
                ]),

            Section::make('Zoekwoorden')
                ->schema([
                    Select::make('keywords')
                        ->label('Gekoppelde zoekwoorden')
                        ->multiple()
                        ->relationship('keywords', 'keyword')
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            $volume = $record->volume_exact ? $record->volume_exact.'/mnd' : ($record->volume_indication ?? '');

                            return "{$record->keyword}".($volume ? " · {$volume}" : '').' · '.$record->type;
                        })
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Interne link-kandidaten')
                ->description('Deze links gebruikt de AI in de bodies. Koppel een model uit het CMS (title en url komen dan live mee), of vul een losse URL in.')
                ->collapsible()
                ->collapsed()
                ->headerActions([
                    Action::make('seed_link_candidates')
                        ->label('Vul uit routes')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Vul link-kandidaten uit routes')
                        ->modalDescription('Dit leest alle route-modellen voor de locale van deze draft en vervangt de huidige lijst.')
                        ->visible(fn ($record) => $record !== null)
                        ->action(function ($record) {
                            $locale = $record->locale ?? app()->getLocale();
                            $candidates = app(LinkCandidatesService::class)->forLocale($locale, 30);

                            $record->linkCandidates()->delete();

                            foreach ($candidates as $index => $c) {
                                $record->linkCandidates()->create([
                                    'sort_order' => $index,
                                    'type' => $c['type'] ?? null,
                                    'title' => (string) ($c['title'] ?? ''),
                                    'url' => (string) ($c['url'] ?? ''),
                                    'subject_type' => $c['subject_type'] ?? null,
                                    'subject_id' => $c['subject_id'] ?? null,
                                ]);
                            }

                            Notification::make()
                                ->title(count($candidates).' link-kandidaten geladen uit routes')
                                ->success()
                                ->send();

                            return redirect(request()->header('Referer') ?: url()->current());
                        }),
                    Action::make('ai_pick_link_candidates')
                        ->label('AI-selectie op onderwerp')
                        ->icon('heroicon-o-sparkles')
                        ->color('primary')
                        ->visible(fn ($record) => $record !== null)
                        ->modalHeading('Laat AI relevante interne links kiezen')
                        ->modalDescription('Geef een onderwerp op. AI selecteert uit alle beschikbare route-modellen de meest relevante pagina\'s en zet die als link-kandidaten.')
                        ->schema([
                            Textarea::make('topic')
                                ->label('Onderwerp')
                                ->placeholder('Bijv. Leren tassen voor vrouwen, duurzame mode, zakelijk reizen')
                                ->rows(3)
                                ->required(),
                            TextInput::make('max')
                                ->label('Maximaal aantal links')
                                ->numeric()
                                ->default(10)
                                ->minValue(1)
                                ->maxValue(30),
                        ])
                        ->action(function (array $data, $record) {
                            $locale = $record->locale ?? app()->getLocale();
                            $pool = app(LinkCandidatesService::class)->allForLocale($locale, 500);

                            if (empty($pool)) {
                                Notification::make()
                                    ->title('Geen route-modellen gevonden voor deze locale')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $topic = trim((string) $data['topic']);
                            $max = (int) ($data['max'] ?? 10);

                            // Pre-filter: score each candidate by how many significant
                            // topic words appear in its title/url. Wide net op de sitemap,
                            // zodat AI alleen een gefocuste shortlist hoeft te beoordelen.
                            $words = preg_split('/\s+/u', mb_strtolower($topic), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) >= 3));

                            $scored = [];
                            foreach ($pool as $c) {
                                $hay = mb_strtolower(($c['title'] ?? '').' '.($c['url'] ?? ''));
                                $score = 0;
                                foreach ($words as $w) {
                                    if (mb_strpos($hay, $w) !== false) {
                                        $score++;
                                    }
                                }
                                $scored[] = ['c' => $c, 'score' => $score];
                            }

                            if (! empty($words)) {
                                $matched = array_values(array_filter($scored, fn ($e) => $e['score'] > 0));
                                $shortlist = ! empty($matched) ? $matched : $scored;
                            } else {
                                $shortlist = $scored;
                            }

                            usort($shortlist, fn ($a, $b) => $b['score'] <=> $a['score']);
                            $shortlist = array_slice($shortlist, 0, 80);

                            // If all topic words hit on a small set, skip AI entirely.
                            $needed = max(1, count($words));
                            $strong = array_values(array_filter($shortlist, fn ($e) => $e['score'] >= $needed));

                            if (! empty($strong) && count($strong) <= $max) {
                                $picked = array_map(fn ($e) => $e['c'], $strong);
                            } else {
                                $indexed = [];
                                $listText = collect($shortlist)->map(function (array $e, int $i) use (&$indexed) {
                                    $indexed[$i] = $e['c'];
                                    $c = $e['c'];

                                    return sprintf('%d | %s | %s | %s', $i, $c['type'] ?? '', $c['title'] ?? '', $c['url'] ?? '');
                                })->implode("\n");

                                $prompt = <<<TXT
Je krijgt een shortlist interne pagina's van de site. Kies de maximaal {$max} meest relevante voor het onderwerp:
"{$topic}"

Lijst (index | type | titel | url):
{$listText}

Regels:
- Alleen indices die in de lijst staan.
- Sorteer op relevantie (meest relevant eerst).
- Beter 3 echt relevante dan 10 matige.

Retourneer JSON: {"indices": [3, 7, 12, ...]}
TXT;

                                try {
                                    $response = Ai::json($prompt) ?? [];
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('AI-aanroep mislukt, fallback op tekstmatch')
                                        ->body($e->getMessage())
                                        ->warning()
                                        ->send();
                                    $response = [];
                                }

                                $indices = is_array($response['indices'] ?? null) ? $response['indices'] : [];
                                $indices = array_values(array_unique(array_filter($indices, fn ($i) => is_numeric($i) && isset($indexed[(int) $i]))));

                                if (! empty($indices)) {
                                    $picked = array_map(fn ($i) => $indexed[(int) $i], $indices);
                                } else {
                                    // AI had no usable output — fall back to top scored shortlist.
                                    $picked = array_map(fn ($e) => $e['c'], array_slice($shortlist, 0, $max));
                                }
                            }

                            if (empty($picked)) {
                                Notification::make()
                                    ->title('Geen matches gevonden voor dit onderwerp')
                                    ->body('Probeer het breder of specifieker te formuleren.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $record->linkCandidates()->delete();
                            foreach ($picked as $order => $c) {
                                $record->linkCandidates()->create([
                                    'sort_order' => $order,
                                    'type' => $c['type'] ?? null,
                                    'title' => (string) ($c['title'] ?? ''),
                                    'url' => (string) ($c['url'] ?? ''),
                                    'subject_type' => $c['subject_type'] ?? null,
                                    'subject_id' => $c['subject_id'] ?? null,
                                ]);
                            }

                            Notification::make()
                                ->title(count($picked).' link-kandidaten geselecteerd')
                                ->body('Bekijk en pas ze desgewenst nog aan voor je regenereert.')
                                ->success()
                                ->send();

                            return redirect(request()->header('Referer') ?: url()->current());
                        }),
                    Action::make('clear_link_candidates')
                        ->label('Verwijder alles')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Alle link-kandidaten verwijderen?')
                        ->modalDescription('Dit haalt elke link-kandidaat van deze draft weg. Bij de eerstvolgende generatie valt de AI terug op de globale route-lijst.')
                        ->visible(fn ($record) => $record !== null && $record->linkCandidates()->exists())
                        ->action(function ($record) {
                            $count = $record->linkCandidates()->count();
                            $record->linkCandidates()->delete();

                            Notification::make()
                                ->title("{$count} link-kandidaten verwijderd")
                                ->success()
                                ->send();

                            return redirect(request()->header('Referer') ?: url()->current());
                        }),
                ])
                ->schema([
                    Repeater::make('linkCandidates')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->label('')
                        ->columns(6)
                        ->schema([
                            Select::make('subject_type')
                                ->label('Koppel aan model (optioneel)')
                                ->helperText('Laat leeg voor een losse URL.')
                                ->options(function () {
                                    $options = [];
                                    try {
                                        foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                                            $name = is_array($entry) ? ($entry['name'] ?? $key) : $key;
                                            $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                                            if ($class) {
                                                $options[$class] = $name;
                                            }
                                        }
                                    } catch (\Throwable) {
                                        //
                                    }

                                    return $options;
                                })
                                ->nullable()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(fn ($set) => $set('subject_id', null))
                                ->columnSpan(2),
                            Select::make('subject_id')
                                ->label('Specifiek record')
                                ->placeholder('Zoek record...')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->visible(fn ($get) => ! empty($get('subject_type')))
                                ->getSearchResultsUsing(function (string $search, $get) {
                                    $class = $get('subject_type');
                                    if (! $class || ! class_exists($class)) {
                                        return [];
                                    }

                                    return $class::query()->limit(50)->get()
                                        ->filter(fn ($m) => stripos(self::recordLabel($m), $search) !== false)
                                        ->mapWithKeys(fn ($m) => [$m->getKey() => self::recordLabel($m)])
                                        ->all();
                                })
                                ->getOptionLabelUsing(function ($value, $get) {
                                    $class = $get('subject_type');
                                    if (! $class || ! class_exists($class) || ! $value) {
                                        return null;
                                    }
                                    $record = $class::find($value);

                                    return $record ? self::recordLabel($record) : null;
                                })
                                ->options(function ($get) {
                                    $class = $get('subject_type');
                                    if (! $class || ! class_exists($class)) {
                                        return [];
                                    }

                                    return $class::query()->limit(50)->get()
                                        ->mapWithKeys(fn ($m) => [$m->getKey() => self::recordLabel($m)])
                                        ->all();
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, $get, $set) {
                                    $class = $get('subject_type');
                                    if (! $state || ! $class || ! class_exists($class)) {
                                        return;
                                    }
                                    $record = $class::find($state);
                                    if (! $record) {
                                        return;
                                    }

                                    $set('title', self::recordLabel($record));
                                    if (method_exists($record, 'getUrl')) {
                                        try {
                                            $set('url', (string) $record->getUrl());
                                        } catch (\Throwable) {
                                            //
                                        }
                                    }
                                    $set('type', class_basename($class));
                                })
                                ->columnSpan(2),
                            TextInput::make('title')
                                ->label('Titel')
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(2048)
                                ->columnSpan(4),
                            TextInput::make('type')
                                ->label('Type')
                                ->placeholder('Page, Product, ...')
                                ->columnSpan(2),
                        ])
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? $state['url'] ?? 'Nieuwe link')
                        ->addable()
                        ->deletable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Structuur en inhoud')
                ->schema([
                    Repeater::make('sections')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->label('')
                        ->schema([
                            TextInput::make('heading')->label('Titel')->required(),
                            Textarea::make('intent')->label('Waar gaat deze sectie over')->rows(2),
                            Placeholder::make('error_message_display')
                                ->label('')
                                ->visible(fn ($get) => ! empty($get('error_message')))
                                ->content(fn ($get) => new HtmlString('<div class="rounded-md bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 p-3 text-sm text-danger-700 dark:text-danger-300"><strong>Laatste fout:</strong> '.e($get('error_message')).'</div>'))
                                ->columnSpanFull(),
                            RichEditor::make('body')
                                ->label('Inhoud')
                                ->toolbarButtons(['bold', 'italic', 'link', 'orderedList', 'bulletList', 'undo'])
                                ->columnSpanFull(),
                        ])
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['heading'] ?? 'Nieuwe sectie')
                        ->addable()
                        ->deletable()
                        ->extraItemActions([
                            Action::make('regenerate_heading')
                                ->label('Herschrijf heading')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action(function (array $arguments, $livewire) {
                                    $uuid = $arguments['item'] ?? null;
                                    if (! $uuid) {
                                        return;
                                    }
                                    $state = $livewire->data['sections'][$uuid] ?? null;
                                    $sectionId = $state['id'] ?? null;
                                    if (! $sectionId) {
                                        Notification::make()
                                            ->title('Sla eerst op voor je een bestaande sectie regenereert')
                                            ->warning()
                                            ->send();

                                        return;
                                    }
                                    RegenerateSectionHeadingJob::dispatch((int) $sectionId);
                                    Notification::make()
                                        ->title('Heading wordt vernieuwd op de achtergrond')
                                        ->body('Ververs de pagina over een moment.')
                                        ->success()
                                        ->send();
                                }),
                            Action::make('generate_body')
                                ->label('Genereer inhoud')
                                ->icon('heroicon-o-sparkles')
                                ->color('primary')
                                ->action(function (array $arguments, $livewire) {
                                    $uuid = $arguments['item'] ?? null;
                                    if (! $uuid) {
                                        return;
                                    }
                                    $state = $livewire->data['sections'][$uuid] ?? null;
                                    $sectionId = $state['id'] ?? null;
                                    if (! $sectionId) {
                                        Notification::make()
                                            ->title('Sla eerst op voor je inhoud genereert')
                                            ->warning()
                                            ->send();

                                        return;
                                    }
                                    GenerateSectionBodyJob::dispatch((int) $sectionId);
                                    Notification::make()
                                        ->title('Inhoud wordt gegenereerd')
                                        ->body('Ververs de pagina over een moment.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('FAQs')
                ->description('Worden automatisch gegenereerd aan het eind van de content-generatie.')
                ->collapsible()
                ->schema([
                    Repeater::make('faqs')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->label('')
                        ->schema([
                            TextInput::make('question')->label('Vraag')->required(),
                            Textarea::make('answer')->label('Antwoord')->rows(3)->required(),
                        ])
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['question'] ?? 'Nieuwe FAQ')
                        ->addable()
                        ->deletable()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->label('Keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('locale')
                    ->label('Taal'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                TextColumn::make('contentCluster.name')
                    ->label('Cluster')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('content_cluster_id')
                    ->label('Cluster')
                    ->relationship('contentCluster', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'In wachtrij',
                        'planning' => 'Planning...',
                        'writing' => 'Schrijven...',
                        'ready' => 'Klaar',
                        'applied' => 'Toegepast',
                        'failed' => 'Mislukt',
                    ]),
                SelectFilter::make('locale')
                    ->label('Taal')
                    ->options([
                        'nl' => 'Nederlands',
                        'en' => 'Engels',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function recordLabel($record): string
    {
        $name = $record->name ?? $record->title ?? ('Record #'.$record->getKey());
        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #'.$record->getKey());
        }

        return (string) $name;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentDrafts::route('/'),
            'create' => CreateContentDraft::route('/create'),
            'edit' => EditContentDraft::route('/{record}/edit'),
        ];
    }
}
