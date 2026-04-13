<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\CreateContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\EditContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\ListContentDrafts;
use Dashed\DashedMarketing\Models\ContentDraft;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class ContentDraftResource extends Resource
{
    protected static ?string $model = ContentDraft::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Content concepten';

    protected static ?string $label = 'Content concept';

    protected static ?string $pluralLabel = 'Content concepten';

    protected static ?int $navigationSort = 21;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Content concept')
                    ->schema([
                        TextInput::make('keyword')
                            ->label('Keyword')
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
                                'planning' => 'Planning...',
                                'writing' => 'Schrijven...',
                                'ready' => 'Klaar',
                                'applied' => 'Toegepast',
                                'failed' => 'Mislukt',
                            ])
                            ->default('pending')
                            ->disabled(),
                        Textarea::make('instruction')
                            ->label('Instructie')
                            ->rows(3)
                            ->columnSpanFull(),
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
