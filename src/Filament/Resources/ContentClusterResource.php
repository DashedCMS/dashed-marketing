<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\ListContentClusters;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\CreateContentCluster;
use Dashed\DashedMarketing\Filament\Resources\ContentClusterResource\Pages\EditContentCluster;

class ContentClusterResource extends Resource
{
    protected static ?string $model = ContentCluster::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Content clusters';

    protected static ?string $label = 'Content cluster';

    protected static ?string $pluralLabel = 'Content clusters';

    protected static ?int $navigationSort = 22;

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
