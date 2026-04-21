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
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\ColorPicker;
use Dashed\DashedMarketing\Models\SocialPillar;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource\Pages\EditSocialPillar;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource\Pages\ListSocialPillars;
use Dashed\DashedMarketing\Filament\Resources\SocialPillarResource\Pages\CreateSocialPillar;

class SocialPillarResource extends Resource
{
    protected static ?string $model = SocialPillar::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Pijlers';

    protected static ?string $label = 'Pijler';

    protected static ?string $pluralLabel = 'Pijlers';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Pijler')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('target_percentage')
                            ->label('Doelpercentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        ColorPicker::make('color')
                            ->label('Kleur'),
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
                TextColumn::make('target_percentage')
                    ->label('Doel %')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Beschrijving')
                    ->limit(60),
            ])
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
            'index' => ListSocialPillars::route('/'),
            'create' => CreateSocialPillar::route('/create'),
            'edit' => EditSocialPillar::route('/{record}/edit'),
        ];
    }
}
