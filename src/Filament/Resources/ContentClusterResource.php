<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\CreateContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\ListContentClusters;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Models\Keyword;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ContentClusterResource extends Resource
{
    protected static ?string $model = ContentCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Content clusters';

    protected static ?string $label = 'Content cluster';

    protected static ?string $pluralLabel = 'Content clusters';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Content cluster')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        Select::make('content_type')
                            ->label('Type')
                            ->options([
                                'blog' => 'Blog',
                                'landing_page' => 'Landingspagina',
                                'category' => 'Categoriepagina',
                                'faq' => 'FAQ pagina',
                                'product' => 'Productpagina',
                                'other' => 'Anders',
                            ])
                            ->required(),
                        Select::make('locale')
                            ->label('Taal')
                            ->options(['nl' => 'Nederlands', 'en' => 'English'])
                            ->default(config('app.locale', 'nl'))
                            ->required()
                            ->live(),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'planned' => 'Gepland',
                                'in_progress' => 'In uitvoering',
                                'done' => 'Klaar',
                            ])
                            ->default('planned'),
                        TextInput::make('theme')
                            ->label('Thema')
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Beschrijving')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Zoekwoorden')
                    ->schema([
                        Select::make('keywords')
                            ->label('Gekoppelde zoekwoorden')
                            ->multiple()
                            ->relationship('keywords', 'keyword')
                            ->preload()
                            ->searchable()
                            ->options(function ($get) {
                                $locale = $get('locale') ?? config('app.locale', 'nl');

                                return Keyword::where('locale', $locale)
                                    ->where('status', '!=', 'rejected')
                                    ->orderBy('keyword')
                                    ->pluck('keyword', 'id');
                            })
                            ->createOptionForm([
                                TextInput::make('keyword')->required()->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, $get) {
                                $locale = $get('locale') ?? config('app.locale', 'nl');

                                return Keyword::create([
                                    'keyword' => $data['keyword'],
                                    'locale' => $locale,
                                    'status' => 'approved',
                                ])->id;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Content concepten (preview)')
                    ->description('Bewerk de AI-voorstellen of verwijder ongewenste, en maak er in bulk drafts van.')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Repeater::make('pending_concepts')
                            ->label(false)
                            ->schema([
                                TextInput::make('title')->label('Titel')->required(),
                                Textarea::make('description')->label('Beschrijving')->rows(2),
                                Select::make('suggested_target_type')
                                    ->label('Target type')
                                    ->options(function () {
                                        $options = [];
                                        try {
                                            foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                                                $name = is_array($entry) ? ($entry['name'] ?? $key) : $key;
                                                $options[$key] = $name;
                                            }
                                        } catch (\Throwable) {
                                            //
                                        }

                                        return $options ?: ['article' => 'Artikel'];
                                    })
                                    ->required()
                                    ->live(),
                                Select::make('target_id')
                                    ->label('Target record')
                                    ->placeholder('Nieuw record aanmaken')
                                    ->options(function ($get) {
                                        $typeKey = $get('suggested_target_type');
                                        if (! $typeKey) {
                                            return [];
                                        }
                                        try {
                                            $entry = cms()->builder('routeModels')[$typeKey] ?? null;
                                            $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                                            if (! $class || ! class_exists($class)) {
                                                return [];
                                            }

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
                                Select::make('keyword_ids')
                                    ->label('Gekoppelde keywords')
                                    ->multiple()
                                    ->options(fn ($record) => $record ? $record->keywords()->pluck('keyword', 'id') : [])
                                    ->preload(),
                                Repeater::make('h2_sections')
                                    ->label('H2 outline')
                                    ->schema([
                                        TextInput::make('heading')->label('Heading')->required(),
                                        Textarea::make('intent')->label('Intent')->rows(2),
                                    ])
                                    ->reorderable()
                                    ->addable()
                                    ->deletable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['heading'] ?? null)
                                    ->columnSpanFull(),
                            ])
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable()
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('theme')
                    ->label('Thema'),
                TextColumn::make('content_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->content_type_label)
                    ->color(fn ($record) => $record->content_type_color),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                TextColumn::make('contentDrafts_count')
                    ->label('Concepten')
                    ->counts('contentDrafts'),
            ])
            ->filters([
                SelectFilter::make('content_type')
                    ->label('Type')
                    ->options([
                        'blog' => 'Blog',
                        'landing_page' => 'Landingspagina',
                        'category' => 'Categoriepagina',
                        'faq' => 'FAQ pagina',
                        'product' => 'Productpagina',
                        'other' => 'Anders',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'planned' => 'Gepland',
                        'in_progress' => 'In uitvoering',
                        'done' => 'Klaar',
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
            'index' => ListContentClusters::route('/'),
            'create' => CreateContentCluster::route('/create'),
            'edit' => EditContentCluster::route('/{record}/edit'),
        ];
    }
}
