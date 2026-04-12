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
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMarketing\Models\KeywordResearch;
use Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource\Pages\ListKeywordResearches;
use Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource\Pages\CreateKeywordResearch;
use Dashed\DashedMarketing\Filament\Resources\KeywordResearchResource\Pages\EditKeywordResearch;

class KeywordResearchResource extends Resource
{
    protected static ?string $model = KeywordResearch::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Zoekwoord onderzoek';

    protected static ?string $label = 'Zoekwoord onderzoek';

    protected static ?string $pluralLabel = 'Zoekwoord onderzoeken';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Zoekwoord onderzoek')
                    ->schema([
                        TextInput::make('seed_keyword')
                            ->label('Seed keyword')
                            ->required()
                            ->maxLength(255),
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
                                'pending' => 'In wachtrij',
                                'running' => 'Bezig...',
                                'done' => 'Klaar',
                                'failed' => 'Mislukt',
                            ])
                            ->default('pending')
                            ->disabled(),
                        TextInput::make('progress_message')
                            ->label('Voortgang')
                            ->disabled()
                            ->nullable(),
                        TextInput::make('error_message')
                            ->label('Foutmelding')
                            ->disabled()
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('seed_keyword')
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
                TextColumn::make('keywords_count')
                    ->label('Keywords')
                    ->counts('keywords'),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'In wachtrij',
                        'running' => 'Bezig...',
                        'done' => 'Klaar',
                        'failed' => 'Mislukt',
                    ]),
                SelectFilter::make('locale')
                    ->label('Taal')
                    ->options([
                        'nl' => 'Nederlands',
                        'en' => 'Engels',
                        'de' => 'Duits',
                        'fr' => 'Frans',
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
            'index' => ListKeywordResearches::route('/'),
            'create' => CreateKeywordResearch::route('/create'),
            'edit' => EditKeywordResearch::route('/{record}/edit'),
        ];
    }
}
