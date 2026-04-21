<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages\CreateSocialHoliday;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages\EditSocialHoliday;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages\ListSocialHolidays;
use Dashed\DashedMarketing\Models\SocialHoliday;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SocialHolidayResource extends Resource
{
    protected static ?string $model = SocialHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Feestdagen';

    protected static ?string $label = 'Feestdag';

    protected static ?string $pluralLabel = 'Feestdagen';

    protected static ?int $navigationSort = 17;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Feestdag')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date')
                            ->label('Datum')
                            ->required(),
                        Select::make('country')
                            ->label('Land')
                            ->options([
                                'NL' => 'Nederland',
                                'BE' => 'België',
                                'DE' => 'Duitsland',
                            ])
                            ->required()
                            ->default('NL'),
                        Toggle::make('auto_remind')
                            ->label('Automatische herinnering')
                            ->default(true),
                        TextInput::make('remind_days_before')
                            ->label('Herinnering X dagen van tevoren')
                            ->numeric()
                            ->minValue(1)
                            ->default(7),
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
                TextColumn::make('date')
                    ->label('Datum')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('country')
                    ->label('Land'),
                IconColumn::make('auto_remind')
                    ->label('Herinnering')
                    ->boolean(),
                TextColumn::make('remind_days_before')
                    ->label('Dagen van tevoren')
                    ->suffix(' dagen'),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label('Land')
                    ->options([
                        'NL' => 'Nederland',
                        'BE' => 'België',
                        'DE' => 'Duitsland',
                    ]),
            ])
            ->defaultSort('date', 'asc')
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
            'index' => ListSocialHolidays::route('/'),
            'create' => CreateSocialHoliday::route('/create'),
            'edit' => EditSocialHoliday::route('/{record}/edit'),
        ];
    }
}
