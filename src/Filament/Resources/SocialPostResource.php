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
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedMarketing\Models\SocialPost;
use Filament\Forms\Components\DateTimePicker;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\EditSocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\ListSocialPosts;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\CreateSocialPost;

class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-share';

    protected static string | UnitEnum | null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Social posts';

    protected static ?string $label = 'Social post';

    protected static ?string $pluralLabel = 'Social posts';

    protected static ?int $navigationSort = 10;

    public static function getPlatformOptions(): array
    {
        $platforms = config('dashed-marketing.platforms', []);

        return array_map(fn ($p) => $p['label'], $platforms);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Post inhoud')
                    ->schema([
                        Select::make('platform')
                            ->label('Platform')
                            ->options(static::getPlatformOptions())
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(SocialPost::STATUSES)
                            ->required()
                            ->default('concept'),
                        Textarea::make('caption')
                            ->label('Caption')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                        TagsInput::make('hashtags')
                            ->label('Hashtags')
                            ->placeholder('#hashtag')
                            ->columnSpanFull(),
                        Textarea::make('alt_text')
                            ->label('Alt-tekst afbeelding')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('image_prompt')
                            ->label('Afbeelding prompt (AI)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Planning')
                    ->schema([
                        Select::make('pillar_id')
                            ->label('Content pijler')
                            ->relationship('pillar', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('campaign_id')
                            ->label('Campagne')
                            ->relationship('campaign', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        DateTimePicker::make('scheduled_at')
                            ->label('Ingepland op')
                            ->nullable(),
                        DateTimePicker::make('posted_at')
                            ->label('Gepost op')
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Resultaat')
                    ->schema([
                        TextInput::make('post_url')
                            ->label('Post URL')
                            ->url()
                            ->nullable()
                            ->columnSpanFull(),
                        KeyValue::make('performance_data')
                            ->label('Prestaties')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->label('Platform')
                    ->formatStateUsing(fn ($state) => config("dashed-marketing.platforms.{$state}.label", $state))
                    ->sortable(),
                TextColumn::make('caption')
                    ->label('Caption')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => SocialPost::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => SocialPost::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('pillar.name')
                    ->label('Pijler')
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->label('Ingepland')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('platform')
                    ->label('Platform')
                    ->options(static::getPlatformOptions()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SocialPost::STATUSES),
                SelectFilter::make('pillar_id')
                    ->label('Pijler')
                    ->relationship('pillar', 'name'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('markPosted')
                    ->label('Markeer als gepost')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SocialPost $record) => $record->status !== 'posted')
                    ->form([
                        TextInput::make('post_url')
                            ->label('Post URL (optioneel)')
                            ->url()
                            ->nullable(),
                    ])
                    ->action(function (SocialPost $record, array $data): void {
                        $record->markAsPosted($data['post_url'] ?? null);
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
            'index' => ListSocialPosts::route('/'),
            'create' => CreateSocialPost::route('/create'),
            'edit' => EditSocialPost::route('/{record}/edit'),
        ];
    }
}
