<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
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
                ->description('Deze links gebruikt de AI in de bodies. Voeg toe, bewerk of verwijder om te sturen welke internal links de tekstgeneratie mag gebruiken.')
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
                        ->action(function ($record, $livewire) {
                            $locale = $record->locale ?? app()->getLocale();
                            $candidates = app(LinkCandidatesService::class)->forLocale($locale, 30);

                            $record->linkCandidates()->delete();

                            foreach ($candidates as $index => $c) {
                                $record->linkCandidates()->create([
                                    'sort_order' => $index,
                                    'type' => $c['type'] ?? null,
                                    'title' => (string) ($c['title'] ?? ''),
                                    'url' => (string) ($c['url'] ?? ''),
                                ]);
                            }

                            $livewire->fillForm();

                            Notification::make()
                                ->title(count($candidates).' link-kandidaten geladen uit routes')
                                ->success()
                                ->send();
                        }),
                ])
                ->schema([
                    Repeater::make('linkCandidates')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->label('')
                        ->columns(6)
                        ->schema([
                            TextInput::make('title')
                                ->label('Titel')
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('url')
                                ->label('URL')
                                ->required()
                                ->maxLength(2048)
                                ->columnSpan(3),
                            TextInput::make('type')
                                ->label('Type')
                                ->placeholder('Page, Product, ...')
                                ->columnSpan(1),
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
