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
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\DB;
use Dashed\DashedMarketing\Models\Keyword;
use Dashed\DashedMarketing\Models\ContentCluster;
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
                Tables\Filters\TernaryFilter::make('show_rejected')
                    ->label('Toon afgewezen')
                    ->placeholder('Verbergen (standaard)')
                    ->trueLabel('Alleen afgewezen')
                    ->falseLabel('Exclusief afgewezen')
                    ->queries(
                        true: fn ($query) => $query->where('status', 'rejected'),
                        false: fn ($query) => $query->where('status', '!=', 'rejected'),
                        blank: fn ($query) => $query->where('status', '!=', 'rejected'),
                    ),
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
                Actions\Action::make('approve')
                    ->label('Goedkeuren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Keyword $record) => $record->status !== 'approved')
                    ->action(fn (Keyword $record) => $record->update(['status' => 'approved'])),
                Actions\Action::make('reject')
                    ->label('Afwijzen')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Keyword $record) => $record->status !== 'rejected')
                    ->action(fn (Keyword $record) => $record->update(['status' => 'rejected'])),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('approve')
                        ->label('Goedkeuren')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'approved'])),
                    Actions\BulkAction::make('reject')
                        ->label('Afwijzen')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status' => 'rejected'])),
                    Actions\BulkAction::make('attach_cluster')
                        ->label('Koppel aan cluster')
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('primary')
                        ->schema(function ($records) {
                            $ids = $records->pluck('id')->all();
                            $locale = $records->first()?->locale ?? config('app.locale', 'nl');

                            return [
                                Radio::make('koppel_modus')
                                    ->label('Modus')
                                    ->options([
                                        'new' => 'Nieuwe cluster aanmaken',
                                        'existing' => 'Toevoegen aan bestaande cluster',
                                    ])
                                    ->default('new')
                                    ->required()
                                    ->live(),

                                Section::make()
                                    ->visible(fn ($get) => $get('koppel_modus') === 'new')
                                    ->schema([
                                        TextInput::make('name')->label('Naam')->required(),
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
                                            ->required()
                                            ->default('blog'),
                                        Select::make('locale')
                                            ->label('Taal')
                                            ->options(['nl' => 'Nederlands', 'en' => 'English'])
                                            ->default($locale)
                                            ->required(),
                                        Textarea::make('description')->label('Beschrijving')->rows(2),
                                        Select::make('keywords')
                                            ->label('Zoekwoorden')
                                            ->multiple()
                                            ->options(Keyword::whereIn('id', $ids)->pluck('keyword', 'id'))
                                            ->default($ids)
                                            ->required(),
                                    ]),

                                Section::make()
                                    ->visible(fn ($get) => $get('koppel_modus') === 'existing')
                                    ->schema([
                                        Select::make('cluster_id')
                                            ->label('Bestaande cluster')
                                            ->options(
                                                ContentCluster::where('locale', $locale)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                            )
                                            ->required()
                                            ->live(),
                                        Placeholder::make('current_keywords')
                                            ->label('Huidige keywords in cluster')
                                            ->content(function ($get) {
                                                $id = $get('cluster_id');
                                                if (! $id) {
                                                    return '-';
                                                }
                                                $cluster = ContentCluster::with('keywords')->find($id);

                                                return $cluster?->keywords->pluck('keyword')->implode(', ') ?: '-';
                                            }),
                                        Select::make('keywords_to_add')
                                            ->label('Toe te voegen keywords')
                                            ->multiple()
                                            ->options(Keyword::whereIn('id', $ids)->pluck('keyword', 'id'))
                                            ->default($ids)
                                            ->required(),
                                    ]),
                            ];
                        })
                        ->action(function (array $data, $records) {
                            if ($data['koppel_modus'] === 'new') {
                                DB::transaction(function () use ($data) {
                                    $cluster = ContentCluster::create([
                                        'name' => $data['name'],
                                        'content_type' => $data['content_type'],
                                        'locale' => $data['locale'],
                                        'description' => $data['description'] ?? null,
                                        'status' => 'planned',
                                    ]);
                                    $cluster->keywords()->attach($data['keywords']);
                                });
                                \Filament\Notifications\Notification::make()
                                    ->title('Cluster "'.$data['name'].'" aangemaakt met '.count($data['keywords']).' keywords')
                                    ->success()
                                    ->send();

                                return;
                            }

                            $cluster = ContentCluster::findOrFail($data['cluster_id']);
                            $cluster->keywords()->syncWithoutDetaching($data['keywords_to_add']);
                            \Filament\Notifications\Notification::make()
                                ->title(count($data['keywords_to_add']).' keywords toegevoegd aan "'.$cluster->name.'"')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    Actions\DeleteBulkAction::make()
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
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
