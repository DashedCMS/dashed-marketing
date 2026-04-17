<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use UnitEnum;
use Filament\Tables;
use Filament\Actions;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages;

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationLabel = 'Zoekwoorden';

    protected static ?string $modelLabel = 'Zoekwoord';

    protected static ?string $pluralModelLabel = 'Zoekwoorden';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keyword')->required(),
            Select::make('locale')
                ->options(['nl' => 'Nederlands', 'en' => 'English'])
                ->default(config('app.locale', 'nl'))
                ->required(),
            Select::make('search_intent')->options([
                'informational' => 'Informational',
                'commercial' => 'Commercial',
                'transactional' => 'Transactional',
                'navigational' => 'Navigational',
            ]),
            Select::make('difficulty')->options([
                'easy' => 'Easy',
                'medium' => 'Medium',
                'hard' => 'Hard',
            ]),
            TextInput::make('volume_exact')->numeric(),
            TextInput::make('cpc')->numeric()->step(0.01),
            Select::make('status')->options([
                'new' => 'Nieuw',
                'approved' => 'Goedgekeurd',
                'rejected' => 'Afgewezen',
            ])->required()->default('new'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('keyword')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('locale')->badge()->sortable(),
                Tables\Columns\TextColumn::make('volume_exact')->label('Volume')->sortable(),
                Tables\Columns\TextColumn::make('search_intent')->badge()->label('Intent')->sortable(),
                Tables\Columns\TextColumn::make('difficulty')->badge()->sortable(),
                Tables\Columns\TextColumn::make('cpc')->money('eur')->sortable(),
                Tables\Columns\TextColumn::make('contentClusters.name')->label('Cluster')->badge(),
                Tables\Columns\TextColumn::make('matched_subject_type')->label('Match')->formatStateUsing(
                    fn ($state, $record) => $state ? class_basename($state).' #'.$record->matched_subject_id : '-',
                ),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('locale')
                    ->options(['nl' => 'Nederlands', 'en' => 'English'])
                    ->default(config('app.locale', 'nl')),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['new' => 'Nieuw', 'approved' => 'Goedgekeurd', 'rejected' => 'Afgewezen']),
                Tables\Filters\SelectFilter::make('search_intent')
                    ->label('Intent')
                    ->options([
                        'informational' => 'Informational',
                        'commercial' => 'Commercial',
                        'transactional' => 'Transactional',
                        'navigational' => 'Navigational',
                    ]),
                Tables\Filters\SelectFilter::make('difficulty')
                    ->options([
                        'easy' => 'Easy',
                        'medium' => 'Medium',
                        'hard' => 'Hard',
                    ]),
                Tables\Filters\Filter::make('cpc')
                    ->schema([
                        TextInput::make('cpc_min')->label('CPC vanaf')->numeric()->step(0.01),
                        TextInput::make('cpc_max')->label('CPC tot')->numeric()->step(0.01),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                filled($data['cpc_min'] ?? null),
                                fn ($q) => $q->where('cpc', '>=', (float) $data['cpc_min']),
                            )
                            ->when(
                                filled($data['cpc_max'] ?? null),
                                fn ($q) => $q->where('cpc', '<=', (float) $data['cpc_max']),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (filled($data['cpc_min'] ?? null)) {
                            $indicators[] = 'CPC vanaf €'.number_format((float) $data['cpc_min'], 2);
                        }
                        if (filled($data['cpc_max'] ?? null)) {
                            $indicators[] = 'CPC tot €'.number_format((float) $data['cpc_max'], 2);
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('approve')
                        ->label('Goedkeuren')
                        ->action(fn ($records) => $records->each->update(['status' => 'approved'])),
                    Actions\BulkAction::make('reject')
                        ->label('Afwijzen')
                        ->action(fn ($records) => $records->each->update(['status' => 'rejected'])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKeywords::route('/'),
            'generate' => Pages\GenerateDrafts::route('/generate'),
        ];
    }
}
