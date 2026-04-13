<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages\CreateSocialIdea;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages\EditSocialIdea;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages\ListSocialIdeas;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedMarketing\Jobs\GenerateBulkPostsFromIdeasJob;
use Dashed\DashedMarketing\Jobs\GenerateSocialPostJob;
use Dashed\DashedMarketing\Models\SocialIdea;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SocialIdeaResource extends Resource
{
    protected static ?string $model = SocialIdea::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Ideeën';

    protected static ?string $label = 'Idee';

    protected static ?string $pluralLabel = 'Ideeën';

    protected static ?int $navigationSort = 11;

    public static function getPlatformOptions(): array
    {
        $platforms = config('dashed-marketing.platforms', []);

        return array_map(fn ($p) => $p['label'], $platforms);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Idee')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('platform')
                            ->label('Platform')
                            ->options(static::getPlatformOptions())
                            ->nullable(),
                        Select::make('status')
                            ->label('Status')
                            ->options(SocialIdea::STATUSES)
                            ->required()
                            ->default('idea'),
                        Select::make('pillar_id')
                            ->label('Content pijler')
                            ->relationship('pillar', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TagsInput::make('tags')
                            ->label('Tags')
                            ->nullable(),
                        Textarea::make('notes')
                            ->label('Notities')
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
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('platform')
                    ->label('Platform')
                    ->formatStateUsing(fn ($state) => $state ? config("dashed-marketing.platforms.{$state}.label", $state) : '-')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => SocialIdea::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'idea' => 'gray',
                        'in_production' => 'warning',
                        'used' => 'success',
                        'archived' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('pillar.name')
                    ->label('Pijler')
                    ->sortable(['pillar_id']),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('Platform')
                    ->options(static::getPlatformOptions()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SocialIdea::STATUSES),
                SelectFilter::make('pillar_id')
                    ->label('Pijler')
                    ->relationship('pillar', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('maakPost')
                    ->label('Maak post')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->action(function (SocialIdea $record): void {
                        $platform = $record->platform ?: array_key_first(config('dashed-marketing.platforms', []) ?? []);

                        if (! $platform) {
                            Notification::make()
                                ->title('Geen platform geconfigureerd')
                                ->body('Stel eerst een platform in op het idee of in de marketing config.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $extraInstructions = trim(
                            'Gebruik dit idee als basis:'
                            ."\nTitel: ".($record->title ?? '')
                            .($record->notes ? "\nNotities: ".$record->notes : '')
                            .(! empty($record->tags) ? "\nTags: ".implode(', ', (array) $record->tags) : '')
                        );

                        GenerateSocialPostJob::dispatch(
                            platform: $platform,
                            subject: $record,
                            pillarId: $record->pillar_id,
                            campaignId: null,
                            toneOverride: null,
                            extraInstructions: $extraInstructions,
                            includeKeywords: false,
                            scheduledAt: null,
                            siteId: $record->site_id ?: Sites::getActive(),
                        );

                        $record->update(['status' => 'in_production']);

                        Notification::make()
                            ->title('Post generatie gestart')
                            ->body('De AI maakt de post op de achtergrond aan.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('generate_posts')
                        ->label('Genereer posts van selectie')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalDescription('Voor elk geselecteerd idee wordt een post gegenereerd via AI. Dit draait in de achtergrond.')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $ids = $records->pluck('id')->all();
                            GenerateBulkPostsFromIdeasJob::dispatch($ids, auth()->id());

                            Notification::make()
                                ->title(count($ids).' posts gepland')
                                ->body('Je krijgt een notificatie zodra ze klaar zijn.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'index' => ListSocialIdeas::route('/'),
            'create' => CreateSocialIdea::route('/create'),
            'edit' => EditSocialIdea::route('/{record}/edit'),
        ];
    }
}
