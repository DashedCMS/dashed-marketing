<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\ListContentClusters;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\CreateContentCluster;

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

                                return \Dashed\DashedMarketing\Models\Keyword::where('locale', $locale)
                                    ->where('status', '!=', 'rejected')
                                    ->orderBy('keyword')
                                    ->pluck('keyword', 'id');
                            })
                            ->createOptionForm([
                                TextInput::make('keyword')->required()->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data, $get) {
                                $locale = $get('locale') ?? config('app.locale', 'nl');

                                return \Dashed\DashedMarketing\Models\Keyword::create([
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

                                        if (empty($options)) {
                                            $options = ['article' => 'Artikel'];
                                        }

                                        return $options;
                                    })
                                    ->required(),
                            ])
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable()
                            ->columns(3)
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
