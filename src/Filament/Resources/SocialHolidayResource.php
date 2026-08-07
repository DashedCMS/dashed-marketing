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
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMarketing\Models\SocialHoliday;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages\EditSocialHoliday;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages\ListSocialHolidays;
use Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource\Pages\CreateSocialHoliday;

class SocialHolidayResource extends Resource
{
    protected static ?string $model = SocialHoliday::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Social media';

    protected static ?string $navigationLabel = 'Feestdagen';

    protected static ?string $label = 'Feestdag';

    protected static ?string $pluralLabel = 'Feestdagen';

    protected static ?int $navigationSort = 17;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Feestdag'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Naam'))
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date')
                            ->label(__('Datum'))
                            ->required(),
                        Select::make('country')
                            ->label(__('Land'))
                            ->options([
                                'NL' => __('Nederland'),
                                'BE' => __('België'),
                                'DE' => __('Duitsland'),
                            ])
                            ->required()
                            ->default('NL'),
                        Toggle::make('auto_remind')
                            ->label(__('Automatische herinnering'))
                            ->default(true),
                        TextInput::make('remind_days_before')
                            ->label(__('Herinnering X dagen van tevoren'))
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
                    ->label(__('Naam'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label(__('Datum'))
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('country')
                    ->label(__('Land')),
                IconColumn::make('auto_remind')
                    ->label(__('Herinnering'))
                    ->boolean(),
                TextColumn::make('remind_days_before')
                    ->label(__('Dagen van tevoren'))
                    ->suffix(__(' dagen')),
            ])
            ->filters([
                SelectFilter::make('country')
                    ->label(__('Land'))
                    ->options([
                        'NL' => __('Nederland'),
                        'BE' => __('België'),
                        'DE' => __('Duitsland'),
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
