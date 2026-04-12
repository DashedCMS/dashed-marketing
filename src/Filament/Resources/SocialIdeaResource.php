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
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Dashed\DashedMarketing\Models\SocialIdea;
use Dashed\DashedMarketing\Models\SocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages\ListSocialIdeas;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages\CreateSocialIdea;
use Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource\Pages\EditSocialIdea;

class SocialIdeaResource extends Resource
{
    protected static ?string $model = SocialIdea::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';

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
                    ->formatStateUsing(fn ($state) => $state ? config("dashed-marketing.platforms.{$state}.label", $state) : '-'),
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
                    }),
                TextColumn::make('pillar.name')
                    ->label('Pijler'),
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
                \Filament\Actions\Action::make('maakPost')
                    ->label('Maak post')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->action(function (SocialIdea $record): void {
                        SocialPost::create([
                            'platform' => $record->platform,
                            'status' => 'concept',
                            'caption' => $record->title . "\n\n" . ($record->notes ?? ''),
                            'pillar_id' => $record->pillar_id,
                            'hashtags' => $record->tags,
                        ]);

                        $record->update(['status' => 'in_production']);

                        Notification::make()
                            ->title('Post aangemaakt als concept')
                            ->success()
                            ->send();
                    }),
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
            'index' => ListSocialIdeas::route('/'),
            'create' => CreateSocialIdea::route('/create'),
            'edit' => EditSocialIdea::route('/{record}/edit'),
        ];
    }
}
