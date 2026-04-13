<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\RelationManagers;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class KeywordsRelationManager extends RelationManager
{
    protected static string $relationship = 'keywords';

    protected static ?string $title = 'Keywords';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('keyword')
                ->label('Keyword')
                ->required(),
            Select::make('search_intent')
                ->label('Intent')
                ->options([
                    'informational' => 'Informational',
                    'commercial' => 'Commercial',
                    'transactional' => 'Transactional',
                    'navigational' => 'Navigational',
                ]),
            Select::make('difficulty')
                ->label('Difficulty')
                ->options([
                    'easy' => 'Easy',
                    'medium' => 'Medium',
                    'hard' => 'Hard',
                ]),
            TextInput::make('volume_exact')
                ->label('Volume')
                ->numeric(),
            TextInput::make('cpc')
                ->label('CPC')
                ->numeric()
                ->step(0.01),
            Select::make('status')
                ->label('Status')
                ->options([
                    'new' => 'Nieuw',
                    'approved' => 'Goedgekeurd',
                    'rejected' => 'Afgewezen',
                ])
                ->required()
                ->default('new'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('keyword')
            ->columns([
                TextColumn::make('keyword')
                    ->label('Keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('volume_exact')
                    ->label('Volume')
                    ->sortable(),
                TextColumn::make('search_intent')
                    ->label('Intent')
                    ->badge(),
                TextColumn::make('difficulty')
                    ->label('Difficulty')
                    ->badge(),
                TextColumn::make('cpc')
                    ->label('CPC')
                    ->money('eur'),
                TextColumn::make('contentClusters.name')
                    ->label('Cluster')
                    ->badge(),
                TextColumn::make('matched_subject_type')
                    ->label('Match')
                    ->formatStateUsing(
                        fn ($state, $record) => $state
                            ? class_basename($state) . ' #' . $record->matched_subject_id
                            : '—',
                    ),
                TextColumn::make('source')
                    ->label('Bron')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'new' => 'Nieuw',
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Afgewezen',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('approve')
                        ->label('Goedkeuren')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'approved'])),
                    BulkAction::make('reject')
                        ->label('Afwijzen')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'rejected'])),
                ]),
            ]);
    }
}
