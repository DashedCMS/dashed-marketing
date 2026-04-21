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
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\TernaryFilter;
use Dashed\DashedMarketing\Models\SocialCampaign;
use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource\Pages\EditSocialCampaign;
use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource\Pages\ListSocialCampaigns;
use Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource\Pages\CreateSocialCampaign;

class SocialCampaignResource extends Resource
{
    protected static ?string $model = SocialCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Campagnes';

    protected static ?string $label = 'Campagne';

    protected static ?string $pluralLabel = 'Campagnes';

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Campagne')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        DatePicker::make('start_date')
                            ->label('Startdatum')
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Einddatum')
                            ->required(),
                        Toggle::make('active')
                            ->label('Actief')
                            ->default(true),
                        Textarea::make('focus')
                            ->label('Focus / doel')
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
                TextColumn::make('start_date')
                    ->label('Start')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Einde')
                    ->date('d-m-Y')
                    ->sortable(),
                IconColumn::make('active')
                    ->label('Actief')
                    ->boolean(),
                TextColumn::make('focus')
                    ->label('Focus')
                    ->limit(60),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Actief'),
            ])
            ->defaultSort('start_date', 'desc')
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
            'index' => ListSocialCampaigns::route('/'),
            'create' => CreateSocialCampaign::route('/create'),
            'edit' => EditSocialCampaign::route('/{record}/edit'),
        ];
    }
}
