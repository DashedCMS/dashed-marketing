<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use UnitEnum;
use Filament\Tables;
use Filament\Actions;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Dashed\DashedMarketing\Models\Keyword;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedMarketing\Models\ContentCluster;
use Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages;

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationLabel = 'Zoekwoorden';

    protected static ?string $modelLabel = 'Zoekwoord';

    protected static ?string $pluralModelLabel = 'Zoekwoorden';

    protected static string|UnitEnum|null $navigationGroup = 'Content marketing';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keyword')->required(),
            Select::make('locale')
                ->options(['nl' => __('Nederlands'), 'en' => __('English')])
                ->default(config('app.locale', 'nl'))
                ->required(),
            Select::make('search_intent')->options([
                'informational' => __('Informational'),
                'commercial' => __('Commercial'),
                'transactional' => __('Transactional'),
                'navigational' => __('Navigational'),
            ]),
            Select::make('difficulty')->options([
                'easy' => __('Easy'),
                'medium' => __('Medium'),
                'hard' => __('Hard'),
            ]),
            TextInput::make('volume_exact')->numeric(),
            TextInput::make('cpc')->numeric()->step(0.01),
            Select::make('status')->options([
                'new' => __('Nieuw'),
                'approved' => __('Goedgekeurd'),
                'rejected' => __('Afgewezen'),
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
                Tables\Columns\TextColumn::make('volume_exact')->label(__('Volume'))->sortable(),
                Tables\Columns\TextColumn::make('search_intent')->badge()->label(__('Intent'))->sortable(),
                Tables\Columns\TextColumn::make('difficulty')->badge()->sortable(),
                Tables\Columns\TextColumn::make('cpc')->money('eur')->sortable(),
                Tables\Columns\TextColumn::make('contentClusters.name')->label(__('Cluster'))->badge(),
                Tables\Columns\TextColumn::make('matched_subject_type')->label(__('Match'))->formatStateUsing(
                    fn ($state, $record) => $state ? class_basename($state).' #'.$record->matched_subject_id : '-',
                ),
                Tables\Columns\TextColumn::make('source')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('show_rejected')
                    ->label(__('Toon afgewezen'))
                    ->placeholder(__('Verbergen (standaard)'))
                    ->trueLabel('Alleen afgewezen')
                    ->falseLabel('Exclusief afgewezen')
                    ->queries(
                        true: fn ($query) => $query->where('status', 'rejected'),
                        false: fn ($query) => $query->where('status', '!=', 'rejected'),
                        blank: fn ($query) => $query->where('status', '!=', 'rejected'),
                    ),
                Tables\Filters\SelectFilter::make('locale')
                    ->options(['nl' => __('Nederlands'), 'en' => __('English')])
                    ->default(config('app.locale', 'nl')),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['new' => __('Nieuw'), 'approved' => __('Goedgekeurd'), 'rejected' => __('Afgewezen')]),
                Tables\Filters\SelectFilter::make('search_intent')
                    ->label(__('Intent'))
                    ->options([
                        'informational' => __('Informational'),
                        'commercial' => __('Commercial'),
                        'transactional' => __('Transactional'),
                        'navigational' => __('Navigational'),
                    ]),
                Tables\Filters\SelectFilter::make('difficulty')
                    ->options([
                        'easy' => __('Easy'),
                        'medium' => __('Medium'),
                        'hard' => __('Hard'),
                    ]),
                Tables\Filters\Filter::make('cpc')
                    ->schema([
                        TextInput::make('cpc_min')->label(__('CPC vanaf'))->numeric()->step(0.01),
                        TextInput::make('cpc_max')->label(__('CPC tot'))->numeric()->step(0.01),
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
                    ->label(__('Goedkeuren'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Keyword $record) => $record->status !== 'approved')
                    ->action(fn (Keyword $record) => $record->update(['status' => 'approved'])),
                Actions\Action::make('reject')
                    ->label(__('Afwijzen'))
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
                        ->label(__('Goedkeuren'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['status' => 'approved'])),
                    Actions\BulkAction::make('reject')
                        ->label(__('Afwijzen'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['status' => 'rejected'])),
                    Actions\BulkAction::make('attach_cluster')
                        ->label(__('Koppel aan cluster'))
                        ->icon('heroicon-o-rectangle-stack')
                        ->color('primary')
                        ->schema(function ($records) {
                            $ids = $records->pluck('id')->all();
                            $locale = $records->first()?->locale ?? config('app.locale', 'nl');

                            return [
                                Radio::make('koppel_modus')
                                    ->label(__('Modus'))
                                    ->options([
                                        'new' => __('Nieuwe cluster aanmaken'),
                                        'existing' => __('Toevoegen aan bestaande cluster'),
                                    ])
                                    ->default('new')
                                    ->required()
                                    ->live(),

                                Section::make()
                                    ->visible(fn ($get) => $get('koppel_modus') === 'new')
                                    ->schema([
                                        TextInput::make('name')->label(__('Naam'))->required(),
                                        Select::make('content_type')
                                            ->label(__('Type'))
                                            ->options([
                                                'blog' => __('Blog'),
                                                'landing_page' => __('Landingspagina'),
                                                'category' => __('Categoriepagina'),
                                                'faq' => __('FAQ pagina'),
                                                'product' => __('Productpagina'),
                                                'other' => __('Anders'),
                                            ])
                                            ->required()
                                            ->default('blog'),
                                        Select::make('locale')
                                            ->label(__('Taal'))
                                            ->options(['nl' => __('Nederlands'), 'en' => __('English')])
                                            ->default($locale)
                                            ->required(),
                                        Textarea::make('description')->label(__('Beschrijving'))->rows(2),
                                        Select::make('keywords')
                                            ->label(__('Zoekwoorden'))
                                            ->multiple()
                                            ->options(Keyword::whereIn('id', $ids)->pluck('keyword', 'id'))
                                            ->default($ids)
                                            ->required(),
                                    ]),

                                Section::make()
                                    ->visible(fn ($get) => $get('koppel_modus') === 'existing')
                                    ->schema([
                                        Select::make('cluster_id')
                                            ->label(__('Bestaande cluster'))
                                            ->options(
                                                ContentCluster::where('locale', $locale)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id')
                                            )
                                            ->required()
                                            ->live(),
                                        Placeholder::make('current_keywords')
                                            ->label(__('Huidige keywords in cluster'))
                                            ->content(function ($get) {
                                                $id = $get('cluster_id');
                                                if (! $id) {
                                                    return '-';
                                                }
                                                $cluster = ContentCluster::with('keywords')->find($id);

                                                return $cluster?->keywords->pluck('keyword')->implode(', ') ?: '-';
                                            }),
                                        Select::make('keywords_to_add')
                                            ->label(__('Toe te voegen keywords'))
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
                                Notification::make()
                                    ->title(__('Cluster ":naam" aangemaakt met :aantal keywords', ['naam' => $data['name'], 'aantal' => count($data['keywords'])]))
                                    ->success()
                                    ->send();

                                return;
                            }

                            $cluster = ContentCluster::findOrFail($data['cluster_id']);
                            $cluster->keywords()->syncWithoutDetaching($data['keywords_to_add']);
                            Notification::make()
                                ->title(__(':aantal keywords toegevoegd aan ":naam"', ['aantal' => count($data['keywords_to_add']), 'naam' => $cluster->name]))
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
