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
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMarketing\Models\SeoImprovement;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages\EditSeoImprovement;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages\ListSeoImprovements;
use Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource\Pages\CreateSeoImprovement;

class SeoImprovementResource extends Resource
{
    protected static ?string $model = SeoImprovement::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'SEO verbeteringen';

    protected static ?string $label = 'SEO verbetering';

    protected static ?string $pluralLabel = 'SEO verbeteringen';

    protected static ?int $navigationSort = 23;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('SEO verbetering')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'analyzing' => 'Bezig met analyseren...',
                                'ready' => 'Klaar voor review',
                                'applied' => 'Toegepast',
                                'failed' => 'Mislukt',
                            ])
                            ->default('analyzing')
                            ->disabled(),
                        Textarea::make('analysis_summary')
                            ->label('Analyse samenvatting')
                            ->rows(4)
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
                TextColumn::make('subject_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->sortable(),
                TextColumn::make('subject_id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                TextColumn::make('analysis_summary')
                    ->label('Samenvatting')
                    ->limit(80),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'analyzing' => 'Bezig met analyseren...',
                        'ready' => 'Klaar voor review',
                        'applied' => 'Toegepast',
                        'failed' => 'Mislukt',
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
            'index' => ListSeoImprovements::route('/'),
            'create' => CreateSeoImprovement::route('/create'),
            'edit' => EditSeoImprovement::route('/{record}/edit'),
        ];
    }
}
